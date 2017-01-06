/*
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements.  See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License.  You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

package kMeansPipeline

import java.io.PrintStream

import org.apache.log4j.{Level, Logger}
import org.apache.spark.ml.Pipeline
import org.apache.spark.ml.feature._
import org.apache.spark.mllib.clustering.{KMeansModel, KMeans}
import org.apache.spark.mllib.linalg.Vector
import org.apache.spark.rdd.RDD
import org.apache.spark.sql.{Row, SQLContext}
import org.apache.spark.{SparkConf, SparkContext}
import scopt.OptionParser

import scala.collection.immutable.HashMap

object SparkKMeansMain {

  private case class Params(
                             input: Seq[String] = Seq.empty,
                             k: Int = 40,
                             numIterations: Int = 1000
                           )

  def main(args: Array[String]) {
    val defaultParams = Params()

    val parser = new OptionParser[Params]("KMeansExample") {
      head("KMeansExample: an example KMeans app for plain text data.")
      opt[Int]("k")
        .text(s"number of topics. default: ${defaultParams.k}")
        .action((x, c) => c.copy(k = x))
      arg[String]("<input>...")
        .text("input paths (directories) to plain text corpora." +
          "  Each text file line should hold 1 document.")
        .unbounded()
        .required()
        .action((x, c) => c.copy(input = c.input :+ x))
    }

    parser.parse(args, defaultParams).map { params =>
      run(params)
    }.getOrElse {
      parser.showUsageAsError
      sys.exit(1)
    }
  }

  private def run(params: Params) {
    System.setProperty("hadoop.home.dir", "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\PB\\hadoopforspark")
    val conf = new SparkConf().setAppName(s"KMeansExample with $params").setMaster("local[*]").set("spark.driver.memory", "2g").set("spark.executor.memory", "2g")
    val sc = new SparkContext(conf)

    Logger.getRootLogger.setLevel(Level.WARN)

    val topic_output = new PrintStream("data/Results.txt")
    // Load documents, and prepare them for KMeans.
    val preprocessStart = System.nanoTime()
    val (input, corpus, vocabArray) =
      preprocess(sc, params.input)
    corpus.cache()
    val actualCorpusSize = corpus.count()
    val actualVocabSize = vocabArray.length
    val preprocessElapsed = (System.nanoTime() - preprocessStart) / 1e9

    println()
    println(s"Corpus summary:")
    println(s"\t Training set size: $actualCorpusSize documents")
    println(s"\t Vocabulary size: $actualVocabSize terms")
    println(s"\t Preprocessing time: $preprocessElapsed sec")
    println()


    topic_output.println()
    topic_output.println(s"Corpus summary:")
    topic_output.println(s"\t Training set size: $actualCorpusSize documents")
    topic_output.println(s"\t Vocabulary size: $actualVocabSize terms")
    topic_output.println(s"\t Preprocessing time: $preprocessElapsed sec")
    topic_output.println()

    // Run KMeans.
    val startTime = System.nanoTime()

    val model = KMeans.train(corpus, params.k, params.numIterations)
    model.save(sc,"data/kmeansmodel")

    println("model print:")
    println(model)

    val clucent=model.clusterCenters

    println("cluster centers:")

    clucent.foreach(f=>println(f))
    //model.save(sc,"data/model")
    val x: String =model.toPMML()



    val elapsed = (System.nanoTime() - startTime) / 1e9

    println(s"Finished training KMeans model.  Summary:")
    println(s"\t Training time: $elapsed sec")


    topic_output.println(s"Finished training KMeans model.  Summary:")
    topic_output.println(s"\t Training time: $elapsed sec")

    val predictions: RDD[Int] = model.predict(corpus)

    val error = model.computeCost(corpus)
    val results = input.zip(corpus).zip(predictions)
    val resultsA = results.collect()
    var hm = new HashMap[Int, Int]
    println("Finals results: ")
    //resultsA.foreach(f=>println(f))
    resultsA.foreach(f => {
      topic_output.println(f._1._1 + ";" + f._1._2 + ";" + f._2)
      if (hm.contains(f._2)) {
        var v = hm.get(f._2).get
        v = v + 1
        hm += f._2 -> v
      }
      else {
        hm += f._2 -> 1
      }
    })

    topic_output.close()
    //predictions.foreach(f=>println(f))
    //println(x)
    sc.stop()
  }

  private def preprocess(sc: SparkContext,
                         paths: Seq[String]): (RDD[(String, String)], RDD[Vector], Array[String]) = {

    val sqlContext = SQLContext.getOrCreate(sc)
    import sqlContext.implicits._

    val rddWords = sc.wholeTextFiles("C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport-fulltext\\bbcsport\\*")
    val text = rddWords.map { case (file, text) => (file,CoreNLP.returnLemma(text.replaceAll("""([\p{Punct}&&[^.$]]|[0-9]|\b\p{IsLetter}{1,2}\b)\s*""", " "))
      .replaceAll("""[\p{Punct}&&[^.]]""", " ").replace("."," ")
      //.replace("lrb","").replace("rrb","")
      )}
    val df2=text.toDF("location","docs")

    val df = sc.wholeTextFiles(paths.mkString(",")).map(f => {
      var ff = f._2.replaceAll("[^a-zA-Z\\s:]", " ")
      ff = ff.replaceAll(":", "")
      // println(ff)
      (f._1, CoreNLP.returnLemma(ff))
    }).toDF("location", "docs")




    val tokenizer = new RegexTokenizer()
      .setInputCol("docs")
      .setOutputCol("rawTokens")
    val stopWordsRemover = new StopWordsRemover()
      .setInputCol("rawTokens")
      .setOutputCol("tokens")

    val countVectorizer = new CountVectorizer()
      .setInputCol("tokens")
      .setOutputCol("features")

    val hashingTF = new HashingTF()
      .setInputCol("tokens").setOutputCol("rawFeatures").setNumFeatures(100000)
    val idf = new IDF().setInputCol("rawFeatures").setOutputCol("features")

    val w2v=new Word2Vec().setInputCol("tokens").setOutputCol("wordvectors").setVectorSize(100).setMinCount(0)


    val pipeline = new Pipeline()
      //.setStages(Array(tokenizer, stopWordsRemover, countVectorizer))
      //.setStages(Array(tokenizer, stopWordsRemover,hashingTF,idf))
      .setStages(Array(tokenizer, stopWordsRemover,w2v))

    val model = pipeline.fit(df2)
    model.save("data/pipelinemodel")
    val documents: RDD[Vector] = model.transform(df2)
      .select("wordvectors")
      .rdd
      .map { case Row(wordvectors: Vector) => wordvectors }

    val tfidf: RDD[String] =model.transform(df2).select("tokens").rdd.flatMap{ f=>
      val ff: Array[String] = f.toString.replace("[WrappedArray", "").replace("]","").replace("(", "").replace(")","").replace(", ",",").split(",")
      ff}

    val tfidftermsArray=tfidf.collect()



    val input = model.transform(df2).select("location", "docs").rdd.map { case Row(location: String, docs: String) => (location, docs) }
    println(model.transform(df2).printSchema())
    val dd=model.transform(df2).rdd.take(1)
      dd.foreach(println(_))

    documents.foreach(f=>println(f))
    (input, documents,
      //model.stages(2).asInstanceOf[CountVectorizerModel].vocabulary
      //model.stages(2).asInstanceOf[Word2VecModel].vocabulary
        tfidftermsArray
      )
  }
}

// scalastyle:on println
