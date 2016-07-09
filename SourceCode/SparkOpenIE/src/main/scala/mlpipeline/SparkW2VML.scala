package mlpipeline

import java.io.PrintStream

import org.apache.log4j.{Level, Logger}
import org.apache.spark.ml.feature.{NGram, StopWordsRemover, Tokenizer, Word2Vec}
import org.apache.spark.rdd.RDD
import org.apache.spark.sql.catalyst.expressions.Substring
import org.apache.spark.sql.{Row, DataFrame, SparkSession}
import org.apache.spark.{SparkConf, SparkContext}
import wordnet.WordNetMain

/**
  * Created by Mayanka on 21-Jun-16.
  */
object SparkW2VML {
  def main(args: Array[String]) {

    // Configuration
    val sparkConf = new SparkConf().setAppName("SparkWordCount").setMaster("local[*]")

    val sc = new SparkContext(sparkConf)

    val spark = SparkSession
      .builder
      .appName("SparkW2VML")
      .master("local[*]")
      .getOrCreate()


    // Turn off Info Logger for Console
    Logger.getLogger("org").setLevel(Level.OFF);
    Logger.getLogger("akka").setLevel(Level.OFF);

    // Read the file into RDD[String]
    val input = sc.textFile("data/sample").map(line => {
      //Getting Lemmatized Form of the word using CoreNLP
      val lemma = CoreNLP.returnLemma(line)
      (0, lemma)
    })

    val inputfilepath="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport-fulltext\\bbcsport\\cricket\\*"
    val rddWords =sc.wholeTextFiles(inputfilepath)
    val text: RDD[(String, String)] = rddWords.map { case (file, text) => (file,CoreNLP.returnLemma(text.
      replaceAll("[^a-zA-Z\\s:]", " ").replaceAll("\"\"[\\p{Punct}&&[^.]]\"\"", " ").replaceAll(","," ").replaceAll("\"\"\\b\\p{IsLetter}{1,2}\\b\"\""," "))) }


    //Creating DataFrame from RDD

    val sentenceData = spark.createDataFrame(text).toDF("labels", "sentence")

    //Tokenizer
    val tokenizer = new Tokenizer().setInputCol("sentence").setOutputCol("words")
    val wordsData = tokenizer.transform(sentenceData)

    //Stop Word Remover
    val remover = new StopWordsRemover()
      .setInputCol("words")
      .setOutputCol("filteredWords")
    val processedWordData = remover.transform(wordsData)

    //NGram
    val ngram = new NGram().setInputCol("filteredWords").setOutputCol("ngrams")
    val ngramDataFrame = ngram.transform(processedWordData)
    ngramDataFrame.take(3).foreach(println)
    //println(ngramDataFrame.printSchema())

    //TFIDF TopWords
    val topWords = TFIDF.getTopTFIDFWords(sc, processedWordData.select("filteredWords").rdd)
    println("TOP WORDS: \n\n"+ topWords.mkString("\n"))


    //Word2Vec Model Generation

    val word2Vec = new Word2Vec()
      .setInputCol("filteredWords")
      .setOutputCol("result")
      .setVectorSize(100)
      .setMinCount(0)
    val model = word2Vec.fit(processedWordData)

    val topic_output = new PrintStream("data/word2vector.txt")

    //Finding synonyms for TOP TFIDF Words using Word2Vec Model
    val x= topWords.map(f => {
      println(f._1+"  : ")
      WordNetMain.getSynonyms(f._1)
      val result: DataFrame = model.findSynonyms(f._1, 10)

      val z =result.toDF().select("word","similarity").rdd.map{case Row(word:String,similarity:Double)=>Vector(word,similarity)}
      //.flatMap(case Row(String,Double)=> (String,Double))

      println("required format: ")
      result.take(3).foreach(println)


      result.printSchema()
      val x =result.toDF().select("word","similarity").rdd.map{case Row(word:String,similarity:Double)=>"("+similarity.toString+","+word+")"}.collect()
      //val x: Array[(String, Long)] =result.toDF().select("word","similarity").rdd.map{case Row(word:String,similarity:Double)=>similarity.toString}.zipWithIndex().collect()
      x.foreach(println)
      var y=""
      x.foreach(ff=>y=y+ff+",")
      println(y+"y:")
      topic_output.println(f._1+","+y)
      //topic_output.println(f._1,z)
    })

    println(x.mkString(" "))







    val y: Array[((String, Double), Int)] =topWords.zipWithIndex
    //y.foreach(f=>println(f+"vvvh"))


    topic_output.flush()
    topic_output.close()

    topWords.map(f => {
      println(f._1+" wordnet synonyms : ")
      WordNetMain.getSynonyms(f._1)})




    spark.stop()
  }

}
