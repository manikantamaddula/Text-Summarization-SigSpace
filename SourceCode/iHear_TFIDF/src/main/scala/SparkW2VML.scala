import java.io.PrintStream

import org.apache.log4j.{Level, Logger}
import org.apache.spark.ml.feature.{NGram, StopWordsRemover, Tokenizer, Word2Vec}
import org.apache.spark.rdd.RDD
import org.apache.spark.sql.{DataFrame, Row, SparkSession}
import org.apache.spark.{SparkConf, SparkContext}

/**
  * Created by by Manikanta on 7/5/2016.
  */
object SparkW2VML {
  def W2VML(sc: SparkContext, spark : SparkSession, inputfiles:String,sportsclass:String) {

    // Configuration
    //val sparkConf = new SparkConf().setAppName("SparkWordCount").setMaster("local[*]")

    //val sc = new SparkContext(sparkConf)

    //val spark = SparkSession
    //  .builder
    //  .appName("SparkW2VML")
    //  .master("local[*]")
    //  .getOrCreate()

    val word2vecpath="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\word2vectoroutput"+"\\"+
        inputfiles.split("/").takeRight(1).head
    //val sportsclass  =inputfiles.split("/")
    println("Test: "+println(sportsclass.mkString(" ")))



    // Turn off Info Logger for Console
    Logger.getLogger("org").setLevel(Level.OFF);
    Logger.getLogger("akka").setLevel(Level.OFF);


    // Read the file into RDD[String]
    val input = sc.textFile(inputfiles+"\\*").map(line => {
      //Getting Lemmatized Form of the word using CoreNLP
      val lemma = CoreNLP.returnLemma(line)
      (0, lemma)
    })

    val inputfilepath="C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport-fulltext\\bbcsport\\cricket\\*"
    val rddWords =sc.wholeTextFiles(inputfiles+"\\*")
    val text: RDD[(String, String)] = rddWords.map { case (file, text) => (file,CoreNLP.returnLemma(text.
    replaceAll("[^a-zA-Z\\s:]", " ").replaceAll("\"\"[\\p{Punct}&&[^.]]\"\"", " ").replaceAll(","," ")
      .replaceAll("\"\"\\b\\p{IsLetter}{1,2}\\b\"\""," ").replaceAll("""\["""," ").replaceAll("""%\]%"""," ")
    )) }


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

    val topic_output = new PrintStream("C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\word2vectoroutput\\"+sportsclass+"/word2vector.txt")

    //Finding synonyms for TOP TFIDF Words using Word2Vec Model
   val x= topWords.map(f => {
      println(f._1+"  : ")
      val result: DataFrame = model.findSynonyms(f._1, 100)

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




    //spark.stop()
  }

}
