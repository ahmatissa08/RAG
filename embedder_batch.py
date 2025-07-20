# embedder_batch.py
import sys
import json
from sentence_transformers import SentenceTransformer

def process_batch(docs_json):
    """
    Charge le modèle une seule fois et traite une liste de documents.
    """
    # Charge le modèle
   # model = SentenceTransformer('all-MiniLM-L6-v2')
    model = SentenceTransformer('paraphrase-multilingual-mpnet-base-v2')
    # Prépare les textes à encoder
    docs = json.loads(docs_json)
    ids = [doc['id'] for doc in docs]
    texts = [doc['text'] for doc in docs]
    
    # Encode tous les textes en une seule commande (très rapide)
    embeddings = model.encode(texts).tolist()
    
    # Prépare le résultat final
    results = {}
    for i in range(len(ids)):
        results[ids[i]] = embeddings[i]
        
    # Renvoie un seul gros JSON avec tous les résultats
    print(json.dumps(results))

if __name__ == "__main__":
    # On lit toutes les données envoyées par PHP depuis l'entrée standard
    input_data = sys.stdin.read()
    process_batch(input_data)