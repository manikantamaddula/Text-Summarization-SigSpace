import edu.stanford.nlp.simple.Document;
import edu.stanford.nlp.simple.Sentence;

import java.util.List;

/**
 * Created by Manikanta on 7/3/2016.
 */
public class CoreNLP {
    public static String returnLemma(String sentence) {

        Document doc = new Document(sentence);
        String lemma="";
        for (Sentence sent : doc.sentences()) {  // Will iterate over two sentences

            List<String> l=sent.lemmas();
            for (int i = 0; i < l.size() ; i++) {
                lemma+= l.get(i) +" ";
            }
            //   System.out.println(lemma);
        }

        return lemma;
    }

    public static String returnNER(String sentence) {

        Document doc = new Document(sentence);
        String ner="";
        for (Sentence sent : doc.sentences()) {  // Will iterate over two sentences

            List<String> l=sent.nerTags();
            for (int i = 0; i < l.size() ; i++) {
                ner+= l.get(i) +" ";
            }
           //System.out.println(ner);
        }

        return ner;
    }

}
