package kMeansPipeline

import java.io.File
import java.io.FileWriter
import java.io.PrintWriter

import org.apache.spark.{SparkContext, SparkConf}
import org.encog.mathutil.matrices.Matrix

import scala.collection.JavaConversions._
import scala.collection.mutable.ArrayBuffer
import scala.io.Source
import scala.util.Random
import org.apache.spark.mllib.linalg.{Matrices, Matrix=>M}

import org.encog.mathutil.rbf.RBFEnum
import org.encog.ml.data.basic.BasicMLData
import org.encog.ml.data.basic.BasicMLDataSet
import org.encog.neural.som.SOM
import org.encog.neural.som.training.basic.BasicTrainSOM
import org.encog.neural.som.training.basic.neighborhood.NeighborhoodRBF

class WordClusterSOM(infile: File, outfile: File,sc:SparkContext) {

  System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark");

  // Configuration
  //val sparkConf = new SparkConf().setAppName("SparkTFIDF").setMaster("local[*]")

  //val sc = new SparkContext(sparkConf)


  // read input data and build dataset
  val words = ArrayBuffer[String]()
  val dataset = new BasicMLDataSet()
  Source.fromFile(infile).getLines().foreach(line => {
      val cols: Array[String] = line.replaceAll("\\[","").replaceAll("\\]","").split(",")
    //cols.foreach(f=>println(f+"Manikanta"))
    //println(cols.length)
      val word = cols(cols.length - 1)
    //cols.slice(0, cols.length - 1).foreach(println)
      val vec: Array[Double] = cols.slice(0, cols.length - 1)
        .map(e => e.toDouble)
      dataset.add(new BasicMLData(vec))
      words += word



  })
  
  // Spark's word2vec gives us word vectors of size 200 ,
  // we want to cluster it onto a 100x100 grid (10000 output neurons).
  val som = new SOM(100, 100*100)
  som.reset()
  val neighborhood = new NeighborhoodRBF(RBFEnum.Gaussian, 100, 100)
  val learningRate = 0.01
  val train = new BasicTrainSOM(som, learningRate, dataset, neighborhood)
  train.setForceWinner(false)
  train.setAutoDecay(1000, 0.8, 0.003, 30, 5) // 1000 epochs, learning rate
                                              // decreased from 0.8-0.003,
                                              // radius decreased from 30-5
  // train network - online training
  (0 until 1000).foreach(i => {
    // randomly select single word vector to train with at each epoch
    val idx = (Random.nextDouble * words.size).toInt
    val data = dataset.get(idx).getInput()
    train.trainPattern(data)
    train.autoDecay()
    Console.println("Epoch %d, Rate: %.3f, Radius: %.3f, Error: %.3f"
      .format(i, train.getLearningRate(), train.getNeighborhood().getRadius(), 
        train.getError()))
  })
  
//  // train network - batch training (takes long time but better results)
//  (0 until 1000).foreach(i => {
//    train.iteration()
//    train.autoDecay()
//    Console.println("Epoch %d, Rate: %.3f, Radius: %.3f, Error: %.3f"
//      .format(i, train.getLearningRate(), train.getNeighborhood().getRadius(), 
//        train.getError()))
//  })
  
  // prediction time
  val writer = new PrintWriter(new FileWriter(outfile), true)
  dataset.getData().zip(words)
    .foreach(dw => {
      val xy= convertToXY(som.classify(dw._1.getInput())) // find BMU id/coords
      writer.println("%s\t%d\t%d".format(dw._2, xy._1, xy._2))
  })
  writer.flush()
  writer.close()

  def convertToXY(pos: Int): (Int, Int) = {
    val x = Math.floor(pos / 100).toInt
    val y = pos - (100 * x)
    (x, y)
  }



  val x =som.getWeights
  val y: Array[Array[Double]] =x.getArrayCopy
  //y.foreach(f=>println(f.mkString(" ")))
  val z=y.map(f=>f.mkString(" "))
  //sc.parallelize(z).saveAsTextFile("data/matrix")

  val x2: Array[Double] =x.toPackedArray
  //x2.foreach(f=>println(f.toString))
  sc.parallelize(x2).coalesce(1,true).saveAsTextFile("data/matrix")




}