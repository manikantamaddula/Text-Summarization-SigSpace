package kMeansPipeline

import java.io.{File, FileWriter, PrintWriter}

import org.apache.spark.SparkContext
import org.apache.spark.mllib.linalg.{Matrix => M}
import org.encog.mathutil.rbf.RBFEnum
import org.encog.ml.data.MLData
import org.encog.ml.data.basic.{BasicMLData, BasicMLDataSet}
import org.encog.neural.som.SOM
import org.encog.neural.som.training.basic.BasicTrainSOM
import org.encog.neural.som.training.basic.neighborhood.NeighborhoodRBF

import scala.collection.JavaConversions._
import scala.collection.mutable.ArrayBuffer
import scala.io.Source
import scala.util.Random

class WordClusterSOM2(infile: File, outfile: File, sc:SparkContext) {

  System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark");

  // Configuration
  //val sparkConf = new SparkConf().setAppName("SparkTFIDF").setMaster("local[*]")
  //val sc = new SparkContext(sparkConf)


  // read input data and build dataset
  val words = ArrayBuffer[String]()
  val dataset: BasicMLDataSet = new BasicMLDataSet()
  Source.fromFile(infile).getLines().foreach(line => {
      val cols: Array[String] = line.split("\t")
    //cols.foreach(f=>println(f+"Manikanta"))
    //println(cols.length)
      val word = cols(0)
    //cols.slice(1, cols.length - 1).foreach(println)
      val vec: Array[Double] = cols.slice(1, cols.length)
        .map(e => e.toDouble)
    vec.foreach(f=>println(f))
      dataset.add(new BasicMLData(vec))
      words += word
  })


  words.foreach(f=>println(f))
  println(dataset.getRecordCount)
  
  // Spark's word2vec gives us word vectors of size 200 ,
  // we want to cluster it onto a 100x100 grid (10000 output neurons).
  val som = new SOM(2, 1*40)
  som.reset()
  val neighborhood = new NeighborhoodRBF(RBFEnum.Gaussian,1,40)
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
    println(idx)
    val data = dataset.get(idx).getInput()
    println(data)
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
      //val xy= convertToXY(som.classify(dw._1.getInput())) // find BMU id/coords
      //writer.println("%s\t%d\t%d".format(dw._2, xy._1, xy._2))
      val xy= som.classify(dw._1.getInput()) // find BMU id
      writer.println("%s\t%d".format(dw._2, xy))
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
  val x2: Array[Double] =x.toPackedArray
  //x2.foreach(f=>println(f.toString))
  sc.parallelize(x2).coalesce(1,true).saveAsTextFile("data/matrix2")




}