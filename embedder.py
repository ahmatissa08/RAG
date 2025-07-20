# embedder.py (Mis à jour avec le modèle supérieur)
import sys
import json
from sentence_transformers import SentenceTransformer

# ==================================================================
# ==          MISE À JOUR AVEC LE NOUVEAU MODÈLE                  ==
# ==================================================================
# 'paraphrase-multilingual-mpnet-base-v2' est plus grand et bien meilleur
# pour comprendre les nuances du français.
model = SentenceTransformer('paraphrase-multilingual-mpnet-base-v2')

def get_embedding(text):
    """Génère un embedding pour le texte donné."""
    # model.encode() retourne un array numpy, on le convertit en liste.
    return model.encode(text).tolist()

if __name__ == "__main__":
    # Ce script lit un argument depuis la ligne de commande.
    # Il est utile pour des tests rapides, mais n'est plus utilisé par le chat en direct.
    if len(sys.argv) > 1:
        input_text = sys.argv[1]
        
        # Calcule le vecteur
        embedding_vector = get_embedding(input_text)
        
        # Imprime le vecteur au format JSON pour que PHP puisse le lire
        print(json.dumps(embedding_vector))
    else:
        print(json.dumps({"error": "Aucun texte fourni en argument."}))