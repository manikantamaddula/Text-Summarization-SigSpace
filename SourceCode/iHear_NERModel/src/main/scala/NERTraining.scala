
import org.apache.spark.SparkConf
import org.apache.spark.SparkContext
import org.apache.spark.rdd.RDD

/**
  * Created by Manikanta on 7/3/2016.
  */
object NERTraining {

  def main(args: Array[String]) {

    System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark")
    val conf = new SparkConf().setAppName(s"NERTrain").setMaster("local[*]").set("spark.driver.memory", "4g").set("spark.executor.memory", "4g")
    val sc = new SparkContext(conf)


    val Domain_Based_Words = sc.broadcast(sc.textFile("data/ner/domainBasedWords").map(CoreNLP.returnLemma(_)).collect())

    //val rddWords = sc.wholeTextFiles("C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\smalldatawhiletesting\\cricket\\*")
    val rddWords = sc.wholeTextFiles("C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport-fulltext\\bbcsport\\*")

    val input = rddWords.map{case (file,text)=>text.replaceAll("\t"," ")}.flatMap(f => {
      val s=f.replaceAll("""([\p{Punct}&&[^.$]]|[0-9]|\b\p{IsLetter}{1,2}\b)\s*""", " ").replaceAll(","," ").replace("."," ")
      .replaceAll(";","").replaceAll("\\t"," ")

      s.replaceAll("\\s\\s"," ").replaceAll("%\\t%","").split(" ")}).map(ff => {
      val f = CoreNLP.returnLemma(ff)
      if (Domain_Based_Words.value.contains(f))
        f.trim+"\t"+"Sports"
      else
        f.trim+"\t"+CoreNLP.returnNER(f).trim
    })

    input.saveAsTextFile("data/trainingdata")
    println(input.collect().mkString("\n"))


  }

}
