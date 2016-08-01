package testingCode

import java.io.{PrintStream, FileWriter, PrintWriter,File}

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
import org.spark_project.jetty.server.Authentication.Wrapped
import scala.collection.JavaConversions._
import scala.collection.mutable

import scala.collection.mutable.ArrayBuffer
import scala.io.Source

/**
  * Created by Manikanta on 7/24/2016.
  */
object testNewDataaAllClasses {

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
    val text: RDD[(String, String)] = rddWords.map { case (file, text) => ("0",CoreNLP.returnLemma(text.
      replaceAll("[^a-zA-Z\\s:]", " ")
      // .replaceAll("\"\"[\\p{Punct}&&[^.]]\"\"", " ")
      //.replaceAll(","," ")
      //.replaceAll("\"\"\\b\\p{IsLetter}{1,2}\\b\"\""," ")
    )) }


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

    val x: RDD[mutable.WrappedArray[String]] =processedWordData.select("filteredWords")
    .rdd.map{ case Row(filteredWords:mutable.WrappedArray[String])=>filteredWords}
    x.foreach{f=>println(f)}
    val y: RDD[Array[String]] =x.map(f=>f.toString().replace("WrappedArray(","").replace(")","").replace(", ",",").split(","))

    y.foreach(f=>println(f))

    val z =y.map(f=>f.mkString(" "))
    z.foreach{f=>println(f)}

    val z3: RDD[(String, String)] =z.map{ f=>("0",f)}
    //y.foreach(f=>f)
    val sentenceData2 = spark.createDataFrame(z3).toDF("labels","words")
      sentenceData2.foreach(f=>println(f))



  }
}
