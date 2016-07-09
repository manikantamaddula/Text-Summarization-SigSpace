package wordnet;

import rita.RiWordNet;

import java.io.FileNotFoundException;
import java.io.PrintStream;

/**
 * Created by Mayanka on 28-Jun-16.
 */
public class WordNetMain {
    //public static void main(String args[]) {
        public static void getSynonyms(String word) throws FileNotFoundException {

            PrintStream topic_output = new PrintStream("data/wordNetVector.txt");

        RiWordNet wordnet = new RiWordNet("C:\\Program Files (x86)\\WordNet\\2.1\\dict");

        // Demo finding a list of related words (synonyms)
        //String word = "sense";
            String result="";
        String[] poss = wordnet.getPos(word);
        for (int j = 0; j < poss.length; j++) {
            System.out.println("\n\nSynonyms for " + word + " (pos: " + poss[j] + ")");
            String[] synonyms = wordnet.getAllSynonyms(word,poss[j],10);





            for (int i = 0; i < synonyms.length; i++) {
                result=result+synonyms[i]+",";
                System.out.println(synonyms[i]);

            }

            topic_output.println(result);
        }

            topic_output.flush();
            topic_output.close();

    }
}
