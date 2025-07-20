# embedding_server.py
from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer
import logging

# Configurer le logging pour ne pas polluer la console
log = logging.getLogger('werkzeug')
log.setLevel(logging.ERROR)

app = Flask(__name__)

# --- CHARGEMENT DU MODÈLE (UNE SEULE FOIS AU DÉMARRAGE) ---
print("Chargement du modèle d'embedding supérieur en mémoire...")
# ANCIENNE LIGNE: model = SentenceTransformer('all-MiniLM-L6-v2')
# NOUVELLE LIGNE :
model = SentenceTransformer('paraphrase-multilingual-mpnet-base-v2')
print("Modèle supérieur chargé. Le serveur est prêt.")
# ---------------------------------------------------------

@app.route('/embed', methods=['POST'])
def embed():
    try:
        data = request.get_json()
        if not data or 'text' not in data:
            return jsonify({'error': 'Le champ "text" est manquant'}), 400
        
        text = data['text']
        vector = model.encode(text).tolist()
        
        return jsonify({'success': True, 'embedding': vector})
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    # Le serveur écoutera sur le port 5000 en local
    app.run(host='127.0.0.1', port=5000)