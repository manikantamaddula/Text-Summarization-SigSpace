package testingCode

import mlpipeline.CoreNLP
import org.apache.spark.rdd.RDD
import org.apache.spark.{SparkContext, SparkConf}
import org.apache.spark.ml.Pipeline
import org.apache.spark.ml.feature.{StopWordsRemover, RegexTokenizer}
import org.apache.spark.mllib.linalg.Vector
import org.apache.spark.sql.{SQLContext, Row}
import org.encog.ml.data.basic.BasicMLDataSet

/**
  * Created by Manikanta on 7/17/2016.
  */
object testTFIDF {


  def main (args: Array[String]) {

    System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark");

    // Configuration
    val sparkConf = new SparkConf().setAppName("SparkTFIDF").setMaster("local[*]")

    val sc = new SparkContext(sparkConf)

    val sqlContext = SQLContext.getOrCreate(sc)
    import sqlContext.implicits._


    val paths="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\smalldatawhiletesting\\cricket\\*"

    val df = sc.wholeTextFiles(paths).map(f => {
      var ff = f._2.replaceAll("[^a-zA-Z\\s:]", " ")
      ff = ff.replaceAll(":", "")
      // println(ff)
      (f._1, CoreNLP.returnLemma(ff))
    }).toDF("location", "docs")


    val tokenizer = new RegexTokenizer()
      .setInputCol("docs")
      .setOutputCol("rawTokens")
    val stopWordsRemover = new StopWordsRemover()
      .setInputCol("rawTokens")
      .setOutputCol("tokens")

    val tf = new org.apache.spark.ml.feature.HashingTF()
      .setInputCol("tokens")
      .setOutputCol("features")
    val idf = new org.apache.spark.ml.feature.IDF()
      .setInputCol("features")
      .setOutputCol("idfFeatures")

    val pipeline = new Pipeline()
      .setStages(Array(tokenizer, stopWordsRemover, tf, idf))

    val model = pipeline.fit(df)

    //model.save("data/pipelinemodel")
    val documents = model.transform(df)
      .select("idfFeatures")
      .rdd
      .map { case Row(features: Vector) => features }

    val input = model.transform(df).select("location", "docs").rdd.map { case Row(location: String, docs: String) => (location, docs) }

    documents.foreach(f=>println(f))

  }



}
