
//import java.io.File


import org.apache.log4j.{Level, Logger}
import org.apache.spark.ml.feature._
import org.apache.spark.rdd.RDD
import org.apache.spark.sql.{DataFrame, Row, SparkSession}
import org.apache.spark.{SparkConf, SparkContext}
import java.io.File
import java.nio.file.{Files, Paths}


//import scala.sys.process._

/**
  * Created by Manikanta on 6/24/2016.
  */
object main {


  def main(args: Array[String]) {

    System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark");

    // Configuration
    val sparkConf = new SparkConf().setAppName("iHearTFIDF").setMaster("local[*]")

    val sc = new SparkContext(sparkConf)

    val spark = SparkSession
      .builder
      .appName("iHearTFIDF")
      .master("local[*]")
      .getOrCreate()


    // Turn off Info Logger for Consolexxx
    Logger.getLogger("org").setLevel(Level.OFF);
    Logger.getLogger("akka").setLevel(Level.OFF);

    // Read the file into RDD[String]
    val inputfilepath="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport-fulltext\\bbcsport"
    val tfidffilepath="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\tfidfoutput2"

    val dirs = getListOfSubDirectories(inputfilepath)

    dirs.foreach(f = f => {
      val folderpath = inputfilepath + "\\" + f + "\\*"
      println(folderpath)
      SparkW2VML.W2VML(sc,spark,inputfilepath + "\\" + f,f)

      val rddWords = sc.wholeTextFiles("C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport-fulltext\\bbcsport\\cricket\\*")
      val text: RDD[(String, String)] = rddWords.map { case (file, text) =>
        (file.split("/").takeRight(2).head+"/"+file.split("/").takeRight(1).head, CoreNLP.returnLemma(text)) }
      val rescaledData: DataFrame = TFIDF.getTFIDFWords(sc, spark, text)

      rescaledData.printSchema()


      val reqdata = rescaledData.select("label","filteredWords", "features").toDF()
      //reqdata.rdd.saveAsTextFile(tfidffilepath + "\\" + f) (commneted to generate word2vec output)
      //reqdata.write.format("com.databricks.spark.csv").save(tfidffilepath + "\\" + f)

      //TFIDF TopWords
      val topWords: Array[(String, Double)] = TFIDF.getTopTFIDFWords(sc, rescaledData.select("filteredWords").rdd)
      println("TOP WORDS: \n\n" + topWords.mkString("\n"))

      //val word2vector=TFIDF.getWord2vec(sc,spark, rescaledData,topWords)(commneted to generate word2vec output)
      val word2vecpath="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\word2vecoutput"
      //word2vector.rdd.saveAsTextFile(word2vecpath+"\\"+f) (commneted to generate word2vec output)

      //Word2Vec building




    })


    // Creating RDDs





    //text.saveAsTextFile("data/all")

    /*
    val input = sc.textFile("data/sample").map(text => {

      //val input = text.map((text) => {
      //Getting Lemmatized Form of the word using CoreNLP
      val lemma = CoreNLP.returnLemma(text)
      println("printing lemma:")
      println(lemma)
      //(0, lemma)
      lemma
    })


    //input.saveAsTextFile("data/input")

    */


    /*
    val sentenceData = spark.createDataFrame(text).toDF("label", "article")

    val tokenizer = new Tokenizer().setInputCol("article").setOutputCol("words")
    val wordsData = tokenizer.transform(sentenceData)

    val remover = new StopWordsRemover()
      .setInputCol("words")
      .setOutputCol("filteredWords")
    val processedWordData= remover.transform(wordsData)

    val hashingTF = new HashingTF()
      .setInputCol("filteredWords").setOutputCol("rawFeatures").setNumFeatures(1000000)
    val featurizedData = hashingTF.transform(processedWordData)
    // alternatively, CountVectorizer can also be used to get term frequency vectors

    val idf = new IDF().setInputCol("rawFeatures").setOutputCol("features")
    val idfModel = idf.fit(featurizedData)
    val rescaledData = idfModel.transform(featurizedData)
    val reqdata=rescaledData.select("filteredWords","features").toDF()


    reqdata.foreach(f=>println(f))

    //tfidfwords.foreach(f=>println(f))

    //rescaledData.saveAsTextFile("data/tfidf")
    //val out=rescaledData.distinct().sort("features").take(20).foreach(println)


    //rescaledData.distinct().select("filteredWords","features", "label").sort("features").take(5).foreach(println)
    //rescaledData.select("filteredWords","features", "label").take(7).foreach(println)

    rescaledData.printSchema()
    */





    spark.stop()
  }

  def getListOfSubDirectories(directoryName: String): Array[String] = {
    new File(directoryName).listFiles.filter(_.isDirectory).map(_.getName)
  }

}
