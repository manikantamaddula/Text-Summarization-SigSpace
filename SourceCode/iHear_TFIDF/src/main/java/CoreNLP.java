import edu.stanford.nlp.simple.Document;
import edu.stanford.nlp.simple.Sentence;

import java.util.List;

/**
 * Created by Manikanta on 6/26/2016.
 */
public class CoreNLP {

    public static String returnLemma(String sentence) {

        Document doc = new Document(sentence);
        String lemma="";
        String x="";
        String token="";

        for (Sentence sent : doc.sentences()) {  // Will iterate over two sentences




            List<String> l=sent.lemmas();

            for (int i = 0; i < l.size() ; i++) {


                lemma+= l.get(i) +" ";
            }

            Document doc2 = new Document(lemma);

            for (Sentence sent2 : doc2.sentences()) {

                List<String> l2=sent2.words();
                for (int i = 0; i < l2.size() ; i++) {


                    token+= l2.get(i) +" ";
                }

            }



            //System.out.println(token);
        }

        return lemma;
    }
}
