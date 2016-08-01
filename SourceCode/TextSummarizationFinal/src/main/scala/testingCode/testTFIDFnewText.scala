package testingCode

import mlpipeline.CoreNLP
import org.apache.spark.ml.PipelineModel
import org.apache.spark.ml.feature.IDFModel
import org.apache.spark.ml.feature.IDFModel.IDFModelWriter
import org.apache.spark.mllib.linalg.Vector
import org.apache.spark.rdd.RDD
import org.apache.spark.sql.{Row, DataFrame, SQLContext}
import org.apache.spark.{SparkContext, SparkConf}

/**
  * Created by Manikanta on 7/17/2016.
  */
object testTFIDFnewText {

  def main(args: Array[String]) {

    System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark");

    // Configuration
    val sparkConf = new SparkConf().setAppName("SparkTFIDF").setMaster("local[*]")

    val sc = new SparkContext(sparkConf)

    val sqlContext = SQLContext.getOrCreate(sc)
    import sqlContext.implicits._

    val documents = sc.textFile("Article.txt")
    val documentseq: RDD[Seq[String]] = documents.map(_.split(" ").toSeq)

    val documentDF: DataFrame =documentseq.map(f=>("0",f)).toDF("label","docs")


    val paths="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\smalldatawhiletesting\\cricket\\*"

    val df = sc.wholeTextFiles("Article.txt").map(f => {
      var ff = f._2.replaceAll("[^a-zA-Z\\s:]", " ")
      ff = ff.replaceAll(":", "")
      // println(ff)
      (f._1, CoreNLP.returnLemma(ff))
    }).toDF("location", "docs")

    //val model=IDFModel.load("data/pipelinemodel/stages/3_idf_781a7f072048")

    val model2=PipelineModel.load("data/pipelinemodel")
    //val idfoutput=model2.transform(documentDF)

    val idfoutput=model2.transform(df).select("idfFeatures")
      .rdd
      .map { case Row(features: Vector) => features }



    //val model=new IDFModel("data/pipelinemodel")
    //model.idf
    //val idfoutput2=model.transform(documentDF)

    idfoutput.foreach(f=>println(f))
  }

}
