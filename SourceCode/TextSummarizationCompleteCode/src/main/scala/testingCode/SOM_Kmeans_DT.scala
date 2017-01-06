package testingCode

import java.io.{PrintStream, File}

import kMeansPipeline.WordClusterSOM
import mlpipeline.{TFIDF, CoreNLP}
import org.apache.log4j.{Level, Logger}
import org.apache.spark.ml.Pipeline
import org.apache.spark.ml.feature.{Word2Vec, StopWordsRemover, Tokenizer}
import org.apache.spark.mllib.classification.{NaiveBayesModel, NaiveBayes}
import org.apache.spark.mllib.clustering.KMeans
import org.apache.spark.mllib.evaluation.MulticlassMetrics
import org.apache.spark.mllib.linalg.{Matrix, Vectors, Vector}
import org.apache.spark.mllib.regression.LabeledPoint
import org.apache.spark.mllib.tree.DecisionTree
import org.apache.spark.mllib.tree.model.DecisionTreeModel
import org.apache.spark.rdd.RDD
import org.apache.spark.sql.SparkSession
import org.apache.spark.{SparkContext, SparkConf}

/**
  * Created by Manikanta on 8/2/2016.
  */
object SOM_Kmeans_DT {

  def main(args: Array[String]) {

    System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark");

    // Configuration
    val sparkConf = new SparkConf().setAppName("iHearWOrd2Vec").setMaster("local[*]").set("spark.driver.memory","8g")
    .set("spark.executor.memory","8g")

    val sc = new SparkContext(sparkConf)

    //val spark = SQLContext.getOrCreate(sc)


    val spark = SparkSession.builder.appName("iHearWOrd2Vec").master("local[*]").getOrCreate()
    import spark.implicits._


    // Turn off Info Logger for Consolexxx
    Logger.getLogger("org").setLevel(Level.OFF);
    Logger.getLogger("akka").setLevel(Level.OFF);

    // Read the file into RDD[String]
    val inputfilepath="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport-fulltext\\bbcsport2"
    val tfidffilepath="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\tfidfoutput2"

    //val inputfilepath="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport-fulltext\\bbcsport\\cricket\\*"
    val rddWords =sc.wholeTextFiles(inputfilepath+"\\*",2000)
    val text: RDD[(String, String)] = rddWords.map { case (file, text) => (file,CoreNLP.returnLemma(text.
      replaceAll("[^a-zA-Z\\s:]", " ")
      .replaceAll("\"\"[\\p{Punct}&&[^.]]\"\"", " ")
      .replaceAll(","," ")
      .replaceAll("\"\"\\b\\p{IsLetter}{1,2}\\b\"\""," "))) }


    //Creating DataFrame from RDD
    val sentenceData = spark.createDataFrame(text).toDF("labels", "sentence")
    //Tokenizer
    val tokenizer = new Tokenizer().setInputCol("sentence").setOutputCol("words")
    val wordsData = tokenizer.transform(sentenceData)

    //Stop Word Remover
    val remover = new StopWordsRemover()
      .setInputCol("words")
      .setOutputCol("filteredWords")
    val processedWordData = remover.transform(wordsData)


    //Word2Vec Model Generation
    val word2Vec = new Word2Vec()
      .setInputCol("filteredWords")
      .setOutputCol("result")
      .setVectorSize(100)
      .setMinCount(0)

    println("word2vec model is done")

    val pipeline = new Pipeline()
      .setStages(Array(tokenizer, remover, word2Vec))
    val model= word2Vec.fit(processedWordData)
    //word2Vec.save("data/word2vec")
    val model3=model.transform(processedWordData)

    model.save("data/word2vecmodel")
    //model.save("C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files/word2vecmodel")

    println("saved word2vec model")

    //TFIDF TopWords
    println("topwords are returned")

    val dirs: Array[String] = getListOfSubDirectories(inputfilepath)

    var toptfidfwords: Array[(String, Double)]=Array()
    val y =dirs.foreach(f => {

      val folderpath = inputfilepath + "\\" + f + "\\*"

      val rddWords2 =sc.wholeTextFiles(folderpath)
      val text2: RDD[(String, String)] = rddWords2.map { case (file, text) => (file,CoreNLP.returnLemma(text.
        replaceAll("[^a-zA-Z\\s:]", " ")
        .replaceAll("\"\"[\\p{Punct}&&[^.]]\"\"", " ")
        .replaceAll(","," ")
        .replaceAll("\"\"\\b\\p{IsLetter}{1,2}\\b\"\""," "))) }


      //Creating DataFrame from RDD
      val sentenceData2 = spark.createDataFrame(text2).toDF("labels", "sentence")
      //Tokenizer
      val tokenizer2 = new Tokenizer().setInputCol("sentence").setOutputCol("words")
      val wordsData2 = tokenizer2.transform(sentenceData2)

      //Stop Word Remover
      val remover2 = new StopWordsRemover()
        .setInputCol("words")
        .setOutputCol("filteredWords")
      val processedWordData2 = remover2.transform(wordsData2)
      val x=TFIDF.getTopTFIDFWords(sc, processedWordData2.select("filteredWords").rdd)
      toptfidfwords=toptfidfwords ++ x
    })

    println("toptfidf words for all classes:")
    //toptfidfwords.foreach(f=>println(f))

    val topwordterms =toptfidfwords.map(f=>f._1)
    //sc.parallelize(topwordterms)
    //sc.broadcast(topwordterms)
    val vec =model.getVectors
    val vec2=vec.select("vector","word")
    val vec3=  vec2.filter(vec("word").isin(topwordterms.toList:_*)).rdd
    vec3.coalesce(1,true).saveAsTextFile("data/wordvector2")

    //input to SOM
    val infile= new File("data/wordvector2/part-00000")
    val outfile=new File("somclusters2.txt")
    new WordClusterSOM(infile,outfile,sc)

    //kMeans on SOM to form signatures for sub categories or topics
    // Load and parse the data for kMeans Input
    val data = sc.textFile("somclusters2.txt")
    val parsedData: RDD[Vector] = data.map(s => Vectors.dense(s.split(' ').map(_.toDouble))).cache()
    val arraydata =data.map{ f=>f.split("\t")}
    val vecdata: RDD[Vector] =arraydata.map{ f=>Vectors.dense(f(1).toDouble,f(2).toDouble)}
    val worddata=arraydata.map{f=>f(0)}

    // Cluster the data into 4 classes using KMeans
    val numClusters = 40
    val numIterations = 1000
    val clusters = KMeans.train(vecdata, numClusters, numIterations)
    // Evaluate clustering by computing Within Set Sum of Squared Errors
    val WSSSE = clusters.computeCost(vecdata)
    println("Within Set Sum of Squared Errors = " + WSSSE)
    // Save and load model
    clusters.save(sc, "data/kMeansOnSOMmodel")
    //val sameModel = KMeansModel.load(sc, "data/kMeansOnSOMmodel")

    val mapClusterIndices =clusters.predict(vecdata)
    val x: RDD[((String, Vector), Int)] =worddata.zip(vecdata).zip(mapClusterIndices)

    val writer = new PrintStream("data/somclusters3.txt")
    x.collect.foreach { f =>
      writer.println(f._1._1.toString+"\t"+f._1._2.toString.replace("[","").replace("]","").replace(",","\t")+"\t"+f._2.toString)
    }

    val nvinput=x.map{f=>new LabeledPoint(f._2,f._1._2)}
    nvinput.foreach(f=>println(f))
    nvinput.saveAsTextFile("data/nvinput")

    // Split data into training (70%) and test (30%).
    val splits = nvinput.randomSplit(Array(0.8, 0.2), seed = 11L)
    val training = splits(0)
    val test = splits(1)

    // Train a DecisionTree model.
    //  Empty categoricalFeaturesInfo indicates all features are continuous.
    val numClasses = 40
    val categoricalFeaturesInfo = Map[Int, Int]()
    val impurity = "gini"
    val maxDepth = 5
    val maxBins = 32

    //val model2 = NaiveBayes.train(training, lambda = 1.0, modelType = "multinomial")

    val model2 = DecisionTree.trainClassifier(training, numClasses, categoricalFeaturesInfo,
      impurity, maxDepth, maxBins)

    val predictionAndLabel = test.map(p => (model2.predict(p.features), p.label))
    predictionAndLabel
    val accuracy = 1.0 * predictionAndLabel.filter(x => x._1 == x._2).count() / test.count()

    //predictionAndLabel.foreach{f=>println(f)}

    println("accuracy of naive bayes model: "+accuracy)
    //predictionAndLabel.foreach(f=>println(f))
    val metrics=new MulticlassMetrics(predictionAndLabel)

    val confmatrix: Matrix =metrics.confusionMatrix
    println("ConfusionMatrix; ")
    confmatrix.rowIter.foreach(f=>println(f))
    val wghtprecision: Double =metrics.weightedPrecision
    val wghtrecall: Double =metrics.weightedRecall
    val wghtfmeasure: Double =metrics.weightedFMeasure
    println("Accuracy: "+metrics.accuracy)
    println("Weighted Precision: "+wghtprecision)
    println("Weighted Recall: "+wghtrecall)
    println("Weighted FMeasure: "+wghtfmeasure)


    // Save and load model
    model2.save(sc, "data/myDecisionTreeModel")
    val sameModel = DecisionTreeModel.load(sc, "data/myDecisionTreeModel")


    /*model2.save(sc, "data/myNaiveBayesModel")
    val sameModel = NaiveBayesModel.load(sc, "data/myNaiveBayesModel")
*/



    spark.stop()
    sc.stop()
  }

  def getListOfSubDirectories(directoryName: String): Array[String] = {
    new File(directoryName).listFiles.filter(_.isDirectory).map(_.getName)
  }

}
