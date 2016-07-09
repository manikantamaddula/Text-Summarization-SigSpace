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

import java.io.PrintStream

import org.apache.log4j.{Level, Logger}
import org.apache.spark.ml.Pipeline
import org.apache.spark.mllib.clustering.{DistributedLDAModel, EMLDAOptimizer, LDA, OnlineLDAOptimizer}
import org.apache.spark.mllib.linalg.Vector
import org.apache.spark.rdd.RDD
import org.apache.spark.sql.{Row, SQLContext}
import org.apache.spark.{SparkConf, SparkContext}
import scopt.OptionParser

/**
  * An example Latent Dirichlet Allocation (LDA) app. Run with
  * {{{
  * ./bin/run-example mllib.LDAExample [options] <input>
  * }}}
  * If you use it as a template to create your own app, please use `spark-submit` to submit your app.
  */
object SparkLDAMain {

  private case class Params(
                             input: Seq[String] = Seq.empty,
                             //input:Seq[String]== sc.wholeTextFiles("data\\20_news_group\\*",)
                             //input: String = "C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\smalldatawhiletesting\\cricket\\*",
                             k: Int = 10,
                             algorithm: String = "em")

  def main(args: Array[String]) {
    val defaultParams = Params()

    val parser = new OptionParser[Params]("LDAFeatureExtraction") {
      head("LDAFeatureExtraction: Topic feature extraction for each class")
      opt[Int]("k")
        .text(s"number of topics. default: ${defaultParams.k}")
        .action((x, c) => c.copy(k = x))
      opt[String]("algorithm")
        .text(s"inference algorithm to use. em and online are supported." +
          s" default: ${defaultParams.algorithm}")
        .action((x, c) => c.copy(algorithm = x))
      arg[String]("<input>...")
        .text("input paths (directories) to plain text corpora." +
          "  Each text file line should hold 1 document.")
        .unbounded()
        .required()
        //.action((x, c) => c.copy(input = c.input :+ x))
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
    val conf = new SparkConf().setAppName(s"LDAFeatureExtraction with $params").setMaster("local[*]").set("spark.driver.memory", "4g").set("spark.executor.memory", "4g")
    val sc = new SparkContext(conf)

    Logger.getRootLogger.setLevel(Level.WARN)

    val topic_output = new PrintStream("data/Results.txt")
    // Load documents, and prepare them for LDA.
    val preprocessStart = System.nanoTime()
    val input:Seq[String]=Seq.empty
          val (corpus, vocabArray, actualNumTokens) =
      preprocess(sc, input)
    corpus.cache()
    val actualCorpusSize = corpus.count()
    val actualVocabSize = vocabArray.length
    val preprocessElapsed = (System.nanoTime() - preprocessStart) / 1e9

    println()
    println(s"Corpus summary:")
    println(s"\t Training set size: $actualCorpusSize documents")
    println(s"\t Vocabulary size: $actualVocabSize terms")
    println(s"\t Training set size: $actualNumTokens tokens")
    println(s"\t Preprocessing time: $preprocessElapsed sec")
    println()


    topic_output.println()
    topic_output.println(s"Corpus summary:")
    topic_output.println(s"\t Training set size: $actualCorpusSize documents")
    topic_output.println(s"\t Vocabulary size: $actualVocabSize terms")
    topic_output.println(s"\t Training set size: $actualNumTokens tokens")
    topic_output.println(s"\t Preprocessing time: $preprocessElapsed sec")
    topic_output.println()

    // Run LDA.
    val lda = new LDA()

    val optimizer = params.algorithm.toLowerCase match {
      case "em" => new EMLDAOptimizer
      //.setMiniBatchFraction(1.0 / actualCorpusSize)
      //add (1.0 / actualCorpusSize) to MiniBatchFraction be more robust on tiny datasets.
      case "online" => new OnlineLDAOptimizer().setMiniBatchFraction(0.05 + 1.0 / actualCorpusSize)
      case _ => throw new IllegalArgumentException(
        s"Only em, online are supported but got ${params.algorithm}.")
    }

    lda.setOptimizer(optimizer)
      .setK(params.k)

    val startTime = System.nanoTime()
    val ldaModel = lda.run(corpus)
    val elapsed = (System.nanoTime() - startTime) / 1e9

    println(s"Finished training LDA model.  Summary:")
    println(s"\t Training time: $elapsed sec")


    topic_output.println(s"Finished training LDA model.  Summary:")
    topic_output.println(s"\t Training time: $elapsed sec")

    if (ldaModel.isInstanceOf[DistributedLDAModel]) {
      val distLDAModel = ldaModel.asInstanceOf[DistributedLDAModel]
      val avgLogLikelihood = distLDAModel.logLikelihood / actualCorpusSize.toDouble
      println(s"\t Training data average log likelihood: $avgLogLikelihood")
      println()
      topic_output.println(s"\t Training data average log likelihood: $avgLogLikelihood")
      topic_output.println()
    }

    // Print the topics, showing the top-weighted terms for each topic.
    val topicIndices = ldaModel.describeTopics(maxTermsPerTopic = actualVocabSize/(params.k*10  ))
    val topics = topicIndices.map { case (terms, termWeights) =>
      terms.zip(termWeights).map { case (term, weight) => (vocabArray(term.toInt), weight) }
    }
    println(s"${params.k} topics:")
    topic_output.println(s"${params.k} topics:")
    topics.zipWithIndex.foreach { case (topic, i) =>
      println(s"TOPIC $i")
      // topic_output.println(s"TOPIC $i")
      topic.foreach { case (term, weight) =>
        println(s"$term\t$weight")
        topic_output.println(s"TOPIC_$i;$term;$weight")
      }
      println()
      topic_output.println()
    }
    topic_output.close()

    val topics2 = ldaModel.topicsMatrix

    for (topic <- Range(0, params.k)) {
      print("Topic " + topic + ":")
      for (word <- Range(0, ldaModel.vocabSize)) { print(" " + topics2(word, topic)); }
      println()
    }
    sc.stop()
  }

  /**
    * Load documents, tokenize them, create vocabulary, and prepare documents as term count vectors.
    *
    * @return (corpus, vocabulary as array, total token count in corpus)
    */
  private def preprocess(
                          sc: SparkContext,
                          paths: Seq[String]): (RDD[(Long, Vector)], Array[String], Long) = {

    val sqlContext = SQLContext.getOrCreate(sc)
    import org.apache.spark.ml.feature._
    import sqlContext.implicits._

    // Get dataset of document texts
    // One document per line in each text file. If the input consists of many small files,
    // this can result in a large number of small partitions, which can degrade performance.
    // In this case, consider using coalesce() to create fewer, larger partitions.
    val df = sc.textFile(paths.mkString(",")).map(f => CoreNLP.returnLemma(f)).toDF("docs")
    println(paths.mkString(",")+"datapath")


    val rddWords = sc.wholeTextFiles("C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport-fulltext\\bbcsport\\cricket\\*")

    val text = rddWords.map { case (file, text) => CoreNLP.returnLemma(text.replaceAll("""([\p{Punct}&&[^.$]]|[0-9]|\b\p{IsLetter}{1,2}\b)\s*""", " "))
      .replaceAll("""[\p{Punct}&&[^.]]""", " ").replace("."," ")
      //.replace("lrb","").replace("rrb","")
       }


    val text2 = rddWords.map { case (file, text) => CoreNLP.returnLemma(text.replaceAll("[^a-zA-Z\\s:]", " "))
      .replaceAll("""[\p{Punct}&&[^.]]""", " ")
      //.replace("lrb","").replace("rrb","")
    }
    val df2=text.toDF("docs")


    val tokenizer = new RegexTokenizer()
      .setInputCol("docs")
      .setOutputCol("rawTokens")

    //val stopwords= StopWordsRemover.loadDefaultStopWords("english") ++Array(".",",",":","?","'s",";","'","\"","''")
    val stopWordsRemover = new StopWordsRemover()
      .setInputCol("rawTokens")
      .setOutputCol("tokens")
      //.setStopWords(stopwords)

    val countVectorizer = new CountVectorizer()
      .setInputCol("tokens")
      .setOutputCol("features")

    val hashingTF = new HashingTF()
      .setInputCol("tokens").setOutputCol("rawFeatures").setNumFeatures(10000)
    val idf = new IDF().setInputCol("rawFeatures").setOutputCol("features")


    val pipeline = new Pipeline()
      //.setStages(Array(tokenizer, stopWordsRemover, countVectorizer))
      .setStages(Array(tokenizer, stopWordsRemover,hashingTF,idf))

    val model = pipeline.fit(df2)
    val documents: RDD[(Long, Vector)] = model.transform(df2)
      .select("features")
      .rdd
      .map { case Row(features: Vector) => features }
      .zipWithIndex()
      .map(_.swap)





    val tfidf: RDD[String] =model.transform(df2).select("tokens").rdd.flatMap{ f=>
      val ff: Array[String] = f.toString.replace("[WrappedArray", "").replace("]","").replace("(", "").replace(")","").replace(", ",",").split(",")
    ff}

    val tfidftermsArray=tfidf.collect()

      //case Row(tokens:Array[String])=> tokens.foreach(ff=>println(ff))}
    //val tfidf2 =tfidf.select("tokens").rdd.map{(arrayCol: mutable.WrappedArray[String]) => arrayCol.mkString(",")}







    //documents.map(_._2.numActives).sum().toLong



    (documents,
     //model.stages(2).asInstanceOf[CountVectorizerModel].vocabulary, // vocabulary
      //model.stages(4).asInstanceOf[IDFModel].vocabulary, // vocabulary
      tfidftermsArray,
      documents.map(_._2.numActives).sum().toLong) // total token count
  }
}

// scalastyle:on println
