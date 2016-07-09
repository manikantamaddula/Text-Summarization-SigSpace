import org.apache.spark.SparkContext
import org.apache.spark.ml.feature._
import org.apache.spark.mllib.feature.{IDF=>mllibIDF, HashingTF=>mllibHashingTF}
import org.apache.spark.rdd.RDD
import org.apache.spark.sql.types.{ArrayType, StringType, StructField, StructType}
import org.apache.spark.sql.{Row, DataFrame, SparkSession}

import scala.collection.immutable.HashMap

/**
  * Created by Manikanta on 6/27/2016.
  */
object TFIDF {

  def getTFIDFWords(sc: SparkContext, spark : SparkSession, text: RDD[(String,String)]): DataFrame = {


    val sentenceData = spark.createDataFrame(text).toDF("label", "article")

    val tokenizer = new Tokenizer().setInputCol("article").setOutputCol("words")
    val wordsData = tokenizer.transform(sentenceData)

    val stopwords= StopWordsRemover.loadDefaultStopWords("english") ++Array(".",",",":","?","'s",";","'","\"","''")

    val remover = new StopWordsRemover()
      .setInputCol("words")
      .setOutputCol("filteredWords")
      .setStopWords(stopwords)
    val processedWordData= remover.transform(wordsData)

    val hashingTF = new HashingTF()
      .setInputCol("filteredWords").setOutputCol("rawFeatures").setNumFeatures(1000000)
    val featurizedData = hashingTF.transform(processedWordData)
    // alternatively, CountVectorizer can also be used to get term frequency vectors

    val idf = new IDF().setInputCol("rawFeatures").setOutputCol("features")
    val idfModel = idf.fit(featurizedData)
    val rescaledData = idfModel.transform(featurizedData)
    val reqdata: DataFrame =rescaledData.select("filteredWords","features").toDF()
    //val mkString=reqdata((filteredWords:String,arrayCol:Array[String])=>arrayCol.mkString(","))


    reqdata.foreach(f=>println(f))

    for(c<-reqdata.select("features")) println(c)

    //tfidfwords.foreach(f=>println(f))

    //rescaledData.saveAsTextFile("data/tfidf")
    //val out=rescaledData.distinct().sort("features").take(20).foreach(println)


    //rescaledData.distinct().select("filteredWords","features", "label").sort("features").take(5).foreach(println)
    //rescaledData.select("filteredWords","features", "label").take(7).foreach(println)
    return rescaledData

    //rescaledData.printSchema()
    //rescaledData.write.format("com.databricks.spark.csv").save("data/tfidf")



  }

  def getWord2vec(sc: SparkContext, spark : SparkSession, rescaledData: DataFrame,topWords: Array[(String, Double)]): DataFrame ={
    val word2Vec = new Word2Vec()
      .setInputCol("filteredWords")
      .setOutputCol("result")
      .setVectorSize(100)
      .setMinCount(0)

    val struct = StructType(StructField("label", StringType, false) ::
      StructField("sentence", StringType, true) ::
      StructField("words", ArrayType(StringType,true), true)::
      StructField("filteredWords",ArrayType(StringType,true), true):: Nil
      //StructField("ngrams", ArrayType(StringType,true), true) :: Nil
    )
    val ss=spark.createDataFrame(sc.parallelize(rescaledData.collect()),struct)

    val model = word2Vec.fit(ss)

    val result = model.transform(ss)

    //result.select("ngrams","result").rdd.saveAsTextFile("data/word2vec")

    result.take(3).foreach(println)

    val toptfdf =topWords.foreach(f=>f._1.toString)
    //Finding synonyms for TOP TFIDF Words using Word2Vec Model


    topWords.foreach(f => {
      println(f._1 + "  : ")
      val result = model.findSynonyms(f._1, 100)
      result.take(5).foreach(println)

    })

    val word2vec =topWords.foreach(f => {
      val result2: RDD[Row] = model.findSynonyms(f._1, 100).rdd
      println(f._1 + "  : ")
      result2.foreach(f=>println(f+"result"))
      val x =result2.foreach(f=>f.toString().replace("[","").replace("]","").split(","))

      val k =(f._1,result2)
      //k._2.foreach(f=>println(f))
    })

    return result


  }

  def getTopTFIDFWords(sc: SparkContext, input:RDD[Row]): Array[(String, Double)] = {

    val documentseq = input.map(_.getList(0).toString.replace("[WrappedArray(","").replace(")]","").replace(", ",",").split(",").toSeq)

    val strData = sc.broadcast(documentseq.collect())
    val hashingTF = new mllibHashingTF
    val tf = hashingTF.transform(documentseq)
    tf.cache()

    val idf = new mllibIDF().fit(tf)
    val tfidf = idf.transform(tf)

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

    val documentData = documentseq.flatMap(_.toList)
    val dd = documentData.map(f => {
      val i = hashingTF.indexOf(f)
      val h = mapp.value
      (f, h(i.toString))
    })

    val dd1: RDD[(String, Double)] =dd.distinct().sortBy(_._2,false)
    dd1.take(20).foreach(f=>{
      //println(f)
    })
    return dd1.take(1000)

  }
}
