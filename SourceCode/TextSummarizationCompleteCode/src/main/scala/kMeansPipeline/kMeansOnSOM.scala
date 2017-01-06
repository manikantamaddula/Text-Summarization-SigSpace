package kMeansPipeline

import java.io.PrintStream

import org.apache.spark.mllib.regression.LabeledPoint
import org.apache.spark.rdd.RDD
import org.apache.spark.{SparkContext, SparkConf}
import org.apache.spark.mllib.clustering.{KMeans, KMeansModel}
import org.apache.spark.mllib.linalg.{Vector, Vectors}

/**
  * Created by Manikanta on 7/22/2016.
  */
object kMeansOnSOM {

  def main(args: Array[String]) {

    System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark");

    // Configuration
    val sparkConf = new SparkConf().setAppName("SparkTFIDF").setMaster("local[*]")

    val sc = new SparkContext(sparkConf)



    // Load and parse the data
    val data = sc.textFile("somclusters2.txt")
    val parsedData: RDD[Vector] = data.map(s => Vectors.dense(s.split(' ').map(_.toDouble))).cache()
    val arraydata =data.map{ f=>f.split("\t")}
    val vecdata: RDD[Vector] =arraydata.map{ f=>Vectors.dense(f(1).toDouble,f(2).toDouble)}
    val worddata=arraydata.map{f=>f(0)}


    // Cluster the data into 5 classes using KMeans
    val numClusters = 10
    val numIterations = 20
    val clusters = KMeans.train(vecdata, numClusters, numIterations)

    // Evaluate clustering by computing Within Set Sum of Squared Errors
    val WSSSE = clusters.computeCost(vecdata)
    println("Within Set Sum of Squared Errors = " + WSSSE)

    // Save and load model
    //clusters.save(sc, "data/kMeansOnSOMmodel")
    val sameModel = KMeansModel.load(sc, "data/kMeansOnSOMmodel")



    val mapClusterIndices =clusters.predict(vecdata)

    val x: RDD[((String, Vector), Int)] =worddata.zip(vecdata).zip(mapClusterIndices)

    val writer = new PrintStream("data/somclusters3.txt")

    x.collect.foreach { f =>

      //writer.println("%s\t%d\t%d".format(f._1._1,f._1._2, f._2))
      writer.println(f._1._1.toString+"\t"+f._1._2.toString.replace("[","").replace("]","").replace(",","\t")+"\t"+f._2.toString)

    }

    //x.foreach{f=>println(f)}

    val nvinput=x.map{f=>new LabeledPoint(f._2,f._1._2)}
    nvinput.foreach(f=>println(f))



  }
}
