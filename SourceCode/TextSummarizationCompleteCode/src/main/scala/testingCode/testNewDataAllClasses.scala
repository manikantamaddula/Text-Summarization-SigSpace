package testingCode

import java.io.{PrintStream, FileWriter, PrintWriter,File}

import breeze.linalg.max
import mlpipeline.CoreNLP
import org.apache.log4j.{Level, Logger}
import org.apache.spark.ml.Pipeline
import org.apache.spark.ml.feature.{Word2Vec, Word2VecModel, StopWordsRemover, Tokenizer}
import org.apache.spark.mllib.classification.NaiveBayesModel
import org.apache.spark.mllib.clustering.KMeansModel
import org.apache.spark.mllib.feature.{Word2VecModel=>W}
import org.apache.spark.mllib.linalg.{Matrix => M, Vectors, Vector, Matrices}
import org.apache.spark.mllib.regression.LabeledPoint
import org.apache.spark.rdd.RDD
import org.apache.spark.sql._
import org.apache.spark.{SparkContext, SparkConf}
import org.encog.mathutil.matrices.Matrix
import org.encog.ml.data.MLData
import org.encog.ml.data.basic.{BasicMLDataSet, BasicMLData}
import org.encog.neural.som.SOM
import scala.collection.JavaConversions._

import scala.collection.mutable.ArrayBuffer
import scala.io.Source

/**
  * Created by Manikanta on 7/24/2016.
  */
object testNewDataAllClasses {

  def main(args: Array[String]) {

  System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark");

  // Configuration
  val sparkConf = new SparkConf().setAppName("SparkTFIDF").setMaster("local[*]")

  val sc = new SparkContext(sparkConf)

  val spark = SparkSession.builder.appName("iHearTFIDF").master("local[*]").getOrCreate()

  //  val sqlContext = SQLContext.getOrCreate(sc)
  import spark.implicits._

  // Turn off Info Logger for Consolexxx
  Logger.getLogger("org").setLevel(Level.OFF);
  Logger.getLogger("akka").setLevel(Level.OFF);


  val rddWords =sc.wholeTextFiles("Article.txt")

  val text2 =Source.fromFile(new File("Article.txt")).getLines().toSeq

  val text3: RDD[String] =sc.parallelize(text2,2)
    text3.foreach(f=>println(f))
    val text4 =text3.map{ f=>("0",CoreNLP.returnLemma(f.
      replaceAll("[^a-zA-Z\\s:]", " ")
      // .replaceAll("\"\"[\\p{Punct}&&[^.]]\"\"", " ")
      //.replaceAll(","," ")
      //.replaceAll("\"\"\\b\\p{IsLetter}{1,2}\\b\"\""," ")
    ) )}

  val text: RDD[(String, String)] = rddWords.map { case (file, text) => ("0",CoreNLP.returnLemma(text.
      replaceAll("[^a-zA-Z\\s:]", " ")
     // .replaceAll("\"\"[\\p{Punct}&&[^.]]\"\"", " ")
      //.replaceAll(","," ")
      //.replaceAll("\"\"\\b\\p{IsLetter}{1,2}\\b\"\""," ")
      )) }


    //Creating DataFrame from RDD
  val sentenceData = spark.createDataFrame(text4).toDF("labels", "sentence")

    //Tokenizer
    val tokenizer = new Tokenizer().setInputCol("sentence").setOutputCol("words")
    val wordsData = tokenizer.transform(sentenceData)

    //Stop Word Remover
    val remover = new StopWordsRemover()
      .setInputCol("words")
      .setOutputCol("filteredWords")
    val processedWordData = remover.transform(wordsData)

    //val word2Vec=Word2VecModel.load("data/word2vecmodel")
    //val model =word2Vec.transform(processedWordData)
    //model.printSchema()

    //model.select("filteredWords","result").foreach(f=>println(f))

    //val vec =model.getVectors

    //val x=W.load(sc,"data/word2vecmodel")
    //val x2=x.transform("cricket")

    //Word2Vec Model Generation
    val word2Vec = new Word2Vec()
      .setInputCol("filteredWords")
      .setOutputCol("result")
      .setVectorSize(100)
      .setMinCount(0)

    println("word2vec model is done")

    val model= word2Vec.fit(processedWordData)

    model.getVectors.select("vector","word").toDF.printSchema()

    val wordvectors =model.getVectors.select("vector","word").toDF.rdd

    //val x4 = wordvectors.map((ff:Row)=>(ff.getAs("vector"),ff.getAs("word")))
    //val x4= wordvectors.map{case Row(vector:Vector,word:String)=>(vector,word.toString)}

      wordvectors.coalesce(1,true).saveAsTextFile("data/testwordvector2")

    val model_w2v=Word2VecModel.load("data/word2vecmodel")
    model_w2v.transform(processedWordData).select("result","filteredWords")
    val newNames = Seq("vector", "word")
    model_w2v.transform(processedWordData).select("result","labels").toDF(newNames:_*).rdd.coalesce(1,true).saveAsTextFile("data/testwordvector3")


    // read input data and build dataset
    val words = ArrayBuffer[String]()
    val dataset = new BasicMLDataSet()
    Source.fromFile(new File("data/testwordvector3/part-00000")).getLines().foreach(line => {
      val cols: Array[String] = line.replaceAll("\\[","").replaceAll("\\]","").replaceAll("WrappedArray\\(", "")
          .replaceAll("\\)","").split(",")
      //cols.foreach(f=>println(f+"Manikanta"))
      //println(cols.length)
      val word = cols(cols.length - 1)
      cols.slice(0, cols.length - 1).foreach(println)
      val vec: Array[Double] = cols.slice(0, cols.length - 1)
        .map(e => e.toDouble)
      dataset.add(new BasicMLData(vec))
      words += word
    })

    //val x2: RDD[Array[Double]] =x4.map{ f=>f._1.toArray}
    //val x3 =x4.map{ f=>(f._2,f._1.toArray)}

    val som = new SOM(100, 10 * 10)
    val line: RDD[String] =sc.textFile("data/matrix/part-00000")
    val rddDouble: RDD[Double] =line.map{ f=>f.toDouble}
    val arrayDouble: Array[Double] =rddDouble.collect()
    var settingMatrix: Matrix =new Matrix(100,100)

    var i=0
    var j=0
    for (i<-0 to 99) {
      for (j<-0 to 99) {
        settingMatrix.set(i,j,arrayDouble(i*100+j))
      }
    }

    som.setWeights(settingMatrix)
    //settingMatrix.getArrayCopy.foreach(f=>println(f.mkString(" ")))
    val writer = new PrintStream("somtestoutput.txt")

    dataset.getData().zip(words)
      .foreach(dw => {
        val xy= convertToXY(som.classify(dw._1.getInput())) // find BMU id/coords
        writer.println("%s\t%d\t%d".format(dw._2, xy._1, xy._2))
      })
    def convertToXY(pos: Int): (Int, Int) = {
      val x = Math.floor(pos / 10).toInt
      val y = pos - (10 * x)
      (x, y)
    }

    //val xx =x3.collect().foreach(f=>{val xy=getCoordinate(f._1,f._2)})

    writer.flush()
    writer.close()


    // Load and parse the data for kMeans classification
    val data = sc.textFile("somtestoutput.txt")
    val parsedData: RDD[Vector] = data.map(s => Vectors.dense(s.split(' ').map(_.toDouble))).cache()
    val arraydata =data.map{ f=>f.split("\t")}
    val vecdata: RDD[Vector] =arraydata.map{ f=>Vectors.dense(f(1).toDouble,f(2).toDouble)}
    val worddata=arraydata.map{f=>f(0)}


    val kmeansmodel = KMeansModel.load(sc, "data/kMeansOnSOMmodel")
    val signatures: RDD[Int] =kmeansmodel.predict(vecdata)
    val x: RDD[((String, Vector), Int)] =worddata.zip(vecdata).zip(signatures)
    val nvinput=x.map{f=>new LabeledPoint(f._2,f._1._2)}
    nvinput.foreach(f=>println(f))


    val classifmodel=NaiveBayesModel.load(sc,"data/myNaiveBayesModel")

    val result=classifmodel.predict(vecdata)
    val result2=classifmodel.predictProbabilities(vecdata)
    result.zip(worddata).zip(result2).foreach(f=>println(f))
    val predictions: RDD[((Double, String), Vector)] =result.zip(worddata).zip(result2)
    predictions.foreach(f=>println(f))
    val predictions2=predictions.collect().zip(text3.collect())

    predictions2.foreach(f=>println(f))

    sc.parallelize(predictions2).foreach{f=>

    /*  if (max(f._1._2)){
      f
    } else {
      ""}*/
    }

    val outtext2 =Source.fromFile(new File("Article.txt")).getLines().toSeq

    val outtext3: RDD[String] =sc.parallelize(text2)
    val outtext4 =text3.map{ f=>
      f
    }

    result.collect().zip(text3.collect()).zip(result2.collect()).foreach(f=>println(f))































  }
}
