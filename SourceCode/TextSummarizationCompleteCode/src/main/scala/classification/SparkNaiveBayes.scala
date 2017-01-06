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

package classification

import java.io.PrintStream

import org.apache.log4j.{Level, Logger}
import org.apache.spark.ml.Pipeline
import org.apache.spark.ml.feature.{CountVectorizer, CountVectorizerModel, RegexTokenizer, StopWordsRemover}
import org.apache.spark.mllib.classification.NaiveBayes
import org.apache.spark.mllib.evaluation.MulticlassMetrics
import org.apache.spark.mllib.linalg.Vector
import org.apache.spark.mllib.regression.LabeledPoint
import org.apache.spark.rdd.RDD
import org.apache.spark.sql.{Row, SQLContext}
import org.apache.spark.{SparkConf, SparkContext}
import scopt.OptionParser

import scala.collection.immutable.HashMap

object SparkNaiveBayes {

  private case class Params(
                             input: Seq[String] = Seq.empty
                           )

  def main(args: Array[String]) {
    val defaultParams = Params()

    val parser = new OptionParser[Params]("NBExample") {
      head("NBExample: an example NB app for plain text data.")
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
    val conf = new SparkConf().setAppName(s"NBExample with $params").setMaster("local[*]").set("spark.driver.memory", "4g").set("spark.executor.memory", "4g")
    val sc = new SparkContext(conf)

    //Logger.getRootLogger.setLevel(Level.WARN)

    val topic_output = new PrintStream("data/NB_Results.txt")
    // Load documents, and prepare them for NB.
    val preprocessStart = System.nanoTime()
    val (input, corpus, vocabArray) =
      preprocess(sc, params.input)

    var hm = new HashMap[String, Int]()
    val IMAGE_CATEGORIES = List("sci.crypt", "sci.electronics", "sci.med", "sci.space")
    var index = 0
    IMAGE_CATEGORIES.foreach(f => {
      hm += IMAGE_CATEGORIES(index) -> index
      index += 1
    })
    val mapping = sc.broadcast(hm)
    val data = input.zip(corpus)
    val featureVector = data.map(f => {
      val location_array = f._1._1.split("/")
      val class_name = location_array(location_array.length - 2)

      new LabeledPoint(hm.get(class_name).get.toDouble, f._2)
    })
    val splits = featureVector.randomSplit(Array(0.6, 0.4), seed = 11L)
    val training = splits(0)
    val test = splits(1)

    val model = NaiveBayes.train(training, lambda = 1.0, modelType = "multinomial")

    val predictionAndLabel = test.map(p => (model.predict(p.features), p.label))


    val accuracy = 1.0 * predictionAndLabel.filter(x => x._1 == x._2).count() / test.count()

    val metrics = new MulticlassMetrics(predictionAndLabel)

    // Confusion matrix
    topic_output.println("Confusion matrix:")
    topic_output.println(metrics.confusionMatrix)

    topic_output.println("Accuracy: " + accuracy)


    sc.stop()
  }

  private def preprocess(sc: SparkContext,
                         paths: Seq[String]): (RDD[(String, String)], RDD[Vector], Array[String]) = {

    val sqlContext = SQLContext.getOrCreate(sc)
    import sqlContext.implicits._
    val df = sc.wholeTextFiles(paths.mkString(",")).map(f => {
      var ff = f._2.replaceAll("[^a-zA-Z\\s:]", " ")
      ff = ff.replaceAll(":", "")
      // println(ff)
      (f._1, CoreNLP.returnLemma(ff))
    }).toDF("location", "docs")

    val rddWords = sc.wholeTextFiles("C:\\Users\\Manikanta\\Documents\\UMKC Subjects\\KDM\\Project Files\\bbcsport-fulltext\\bbcsport\\*")
    val text = rddWords.map { case (file, text) => (file,CoreNLP.returnLemma(text.replaceAll("""([\p{Punct}&&[^.$]]|[0-9]|\b\p{IsLetter}{1,2}\b)\s*""", " "))
      .replaceAll("""[\p{Punct}&&[^.]]""", " ").replace(":"," ")
      //.replace("lrb","").replace("rrb","")
      )}
    val df2=text.toDF("location","docs")



    val tokenizer = new RegexTokenizer()
      .setInputCol("docs")
      .setOutputCol("rawTokens")
    val stopWordsRemover = new StopWordsRemover()
      .setInputCol("rawTokens")
      .setOutputCol("tokens")

    val countVectorizer = new CountVectorizer()
      .setInputCol("tokens")
      .setOutputCol("features")

    val pipeline = new Pipeline()
      .setStages(Array(tokenizer, stopWordsRemover, countVectorizer))

    val model = pipeline.fit(df)
    val documents = model.transform(df)
      .select("features")
      .rdd
      .map { case Row(features: Vector) => features }

    val input = model.transform(df).select("location", "docs").rdd.map { case Row(location: String, docs: String) => (location, docs) }
    println(model.transform(df).printSchema())
    (input, documents,
      model.stages(2).asInstanceOf[CountVectorizerModel].vocabulary)
  }
}

// scalastyle:on println
