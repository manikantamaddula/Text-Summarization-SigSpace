# Text Summarization SigSpace

The overall objective is to build an application which can recognize topic data and present summary text.

Text Mining is one of the research areas that has been gaining lot of interest these days. The increasing availability of online information has necessitated intensive research in the area of automatic text summarization within the Natural Language Processing (NLP) community. Summarization technique preserves important information while reducing the original document. Text summarization can be implemented using Text classification. Any text mining application needs a corpus and the data (or features) that is fed to classification machine learning algorithm is huge. This study focuses on optimizing computing of machine learning model by using SigSpace model. SigSpace model uses signatures instead of raw features reducing storage space drastically and it implements other features like independent learning, distributed learning, optimized accuracy, fuzzy matching etc. Finally, evaluation is done comparing different methodologies.

As the name says SigSpace uses signatures of a class as features to classification algorithm. In SigSpace, class based model was built by an evaluation and extension of existing machine learning models i.e., K-Means and Self Organizing Maps (SOM). The machine learning with SigSpace was modeled as a feature set with standard machine learning algorithms (e.g., Naive Bayes, Random Forests, Decision Tree) as well as a class model using L1 (Manhattan distance) and L2 (Euclidean distance) norms. SigSpace author Pradyumna, D. evaluated the model for Image Clustering and Audio classification. This project is one of the first implementations of SigSpace to text mining and classification.

Workflow:
Preprocessing is the first stage to clean text data and remove unnecessary data etc. Next, raw features are generated using stop word removal, lemmatization, Tokenizer, TFIDF, Word2vec. Using SOM data dimensions are reduced and then using KMeans, signatures of data are produced. These signatures are given to Naive Bayes Classification algorithm.

Summarization:
Now for an input article the same workflow is applied using saved models. And features will be generated for input document in similar manner. And classification is done for individual sentences in the put document. So, sentences fall into different topics or classes. Sentences which group into same class will hold the key point information of the article. And on top of this a threshold of Naive Bayes predicted probability is used to filter more sentences. Summarization is implmented for 4 classes in this project.

Dataset:
wikipedia, 20 News groups, BBC Sports

The size of the word vector representation sent as input to SOM is of 60.4 MB (60400 KB). And the size of signatures of all the classes combined together is 538 KB which is just 0.89 % of raw feature data and 1 % of raw text data. Here data reduction is done drastically. As the number of classes increased, the size of the signatures increased but it is minimal.

The presented approach can be said as Global SigSpace since the workflow is not implemented for each class individually. And models at different stages had be saved, so, it is code book dependent. The data reduction has been done drastically that it adds value in computing power and storage. Original data is not lost during process and distributed learning is also implemented. This model can be extended to implement incremental learning.