package kMeansPipeline

import java.io.File
import kMeansPipeline.WordClusterSOM
import org.apache.spark.sql.SQLContext
import org.apache.spark.{SparkContext, SparkConf}


/**
  * Created by Manikanta on 7/6/2016.
  */
object SOMClusterGen {

  def main(args: Array[String]) {

    System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark");

    // Configuration
    val sparkConf = new SparkConf().setAppName("iHearTFIDF").setMaster("local[*]")

    val sc = new SparkContext(sparkConf)

    val spark = SQLContext.getOrCreate(sc)


    //val infile= new File("C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Assignment on tutorial6\\Spark-W2Vec\\data/word2vector.txt")

    val infile= new File("C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Assignment on tutorial6\\Spark-W2Vec\\data/wordvector2/part-00000")

    val outfile=new File("somclusters2.txt")

    val x=new WordClusterSOM(infile,outfile,sc)


  }
}
