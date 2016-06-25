import org.apache.spark.mllib.feature.{HashingTF, IDF}
import org.apache.spark.{SparkConf, SparkContext}
import scala.collection.immutable.HashMap


/**
  * Created by Manikanta on 6/24/2016.
  */
object iHear_TFIDF {


  def main(args: Array[String]) {

    System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark");

    val sparkConf = new SparkConf().setAppName("iHearTFIDF").setMaster("local[*]")

    val sc = new SparkContext(sparkConf)

    // Creating RDDs
    val rddWords = sc.wholeTextFiles("C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport-fulltext\\bbcsport\\cricket\\*")
    val text = rddWords.map { case (file, text) => text }
    // improving the tokenisation
    def tokenize(line: String): Seq[String] = {
      line.split(" ")
        .toSeq
    }
    val tokens = text.map(doc => tokenize(doc))
    // println(tokens.first.take(20))
    //tokens.foreach(f=>println(f))

    val strData = sc.broadcast(tokens.collect())

    val hashingTF = new HashingTF()
    val tf = hashingTF.transform(tokens)
    tf.cache()
    //println("TF's:")
    //tf.foreach(f => println(f))

    val idf = new IDF().fit(tf)
    val tfidf = idf.transform(tf)
    //tfidf.foreach(f => println(f))
    val outputfilepath="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport"
    tfidf.saveAsTextFile(outputfilepath)


    val tfidfvalues = tfidf.flatMap(f => {
      val ff: Array[String] = f.toString.replace(",[", ";").split(";")
      val values = ff(2).replace("]", "").replace(")","").split(",")
      values
    })

    val tfidfindex = tfidf.flatMap(f => {
      val ff: Array[String] = f.toString.replace(",[", ";").split(";")
      val indices = ff(1).replace("]", "").replace(")","").split(",")
      indices
    })
    //tfidf.foreach(f => println(f))


    val tfidfData = tfidfindex.zip(tfidfvalues)
    var hm = new HashMap[String, Double]
    tfidfData.collect().foreach(f => {
      hm += f._1 -> f._2.toDouble
    })
    val mapp = sc.broadcast(hm)

    val documentData = text.flatMap(_.toList)
    val dd = documentData.map(f => {
      val i = hashingTF.indexOf(f)
      val h = mapp.value
      (f, h(i.toString))
    })

    val outputfilepath2="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport2"
    dd.saveAsTextFile(outputfilepath2)

    val dd1=dd.distinct().sortBy(_._2,false)
    dd1.take(20).foreach(f=>{
      //println(f)
    })



  }


}
