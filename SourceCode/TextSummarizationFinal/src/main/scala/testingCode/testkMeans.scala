package testingCode

import java.io.File

import mlpipeline.CoreNLP
import org.apache.log4j.{Level, Logger}
import org.apache.spark.ml.PipelineModel
import org.apache.spark.mllib.clustering.KMeansModel
import org.apache.spark.mllib.linalg.Vector
import org.apache.spark.rdd.RDD
import org.apache.spark.sql.{Row, SparkSession}
import org.apache.spark.{SparkContext, SparkConf}

import scala.io.Source

/**
  * Created by Manikanta on 7/26/2016.
  */
object testkMeans {

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


    val rddWords = sc.wholeTextFiles("Article.txt")

    val text2 = Source.fromFile(new File("Article.txt")).getLines().toSeq

    val text3: RDD[String] = sc.parallelize(text2, 2)
    text3.foreach(f => println(f))
    val text4 = text3.map { f => ("0", CoreNLP.returnLemma(f.
      replaceAll("[^a-zA-Z\\s:]", " ")
      // .replaceAll("\"\"[\\p{Punct}&&[^.]]\"\"", " ")
      //.replaceAll(","," ")
      //.replaceAll("\"\"\\b\\p{IsLetter}{1,2}\\b\"\""," ")
    ))
    }


    val sentenceData = spark.createDataFrame(text4).toDF("labels", "sentence")

    val model = PipelineModel.load("pipelinemodel")

    model.transform(sentenceData)

    val documents: RDD[Vector] = model.transform(sentenceData)
      .select("wordvectors")
      .rdd
      .map { case Row(wordvectors: Vector) => wordvectors }


    val kmeansmodel = KMeansModel.load(sc, "data/kmeansmodel")
    val kmeanspredictions = kmeansmodel.predict(documents)

    kmeanspredictions.collect().zip(text3.collect()).foreach(f => println(f))


  }

  }
