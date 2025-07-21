Assistant Conversationnel Intelligent pour l'IAM
Ce projet est une application web complète développée dans le cadre d’un projet de fin d’études, qui implémente un assistant conversationnel intelligent pour l’Institut Africain de Management (IAM).

Son objectif principal est de fournir un point de contact automatisé, fiable et disponible 24h/24 et 7j/7, capable de répondre aux questions des prospects et étudiants, tout en offrant une interface de gestion puissante aux administrateurs.


✨ Fonctionnalités principales
🤖 IA Hybride (RAG + Simulation de Recherche Web)
Combine une base de données locale MySQL et une recherche web simulée.

Fournit des réponses pertinentes, précises et actualisées.

🛠️ Panneau d'administration complet
Visualisation des statistiques d'utilisation.

CRUD de la base de connaissances avec mise à jour automatique des embeddings IA.

Gestion des utilisateurs, des rôles et des tickets de support.

Génération de rapports PDF téléchargeables.

🔐 Gestion des rôles et accès
Trois types d'utilisateurs avec fonctionnalités dédiées :

Prospect

Étudiant

Administrateur

💡 Fonctionnalités UX avancées
Prise de rendez-vous pour prospects.

Escalade vers un conseiller humain si nécessaire.

Reconnaissance et synthèse vocale intégrées.

🔑 Authentification sécurisée
Utilisation de JSON Web Tokens (JWT) pour la gestion des sessions.

🧱 Architecture technique
Composant	Description
Frontend	HTML5, CSS3 (TailwindCSS), JavaScript (ES6+)
Backend	PHP 8+ avec architecture REST
Base de données	MySQL
Micro-service IA	Python + Flask
Modèle de vectorisation	paraphrase-multilingual-mpnet-base-v2
LLM	OpenAI GPT-3.5 Turbo

🚀 Installation locale
⚙️ Prérequis
PHP 8+ (via XAMPP ou équivalent)

MySQL

Python 3.8+

composer (PHP)

pip (Python)

1. Cloner le projet
bash
Copier
Modifier
git clone https://github.com/ahmatissa08/RAG.git
cd RAG
2. Configurer la base de données
Lancer Apache et MySQL depuis XAMPP.

Créer une base de données nommée chatbot_iam_db via phpMyAdmin.

Importer le fichier database.sql fourni dans le dépôt.

3. Configurer les variables d’environnement
Créer un fichier .env à la racine du projet :

env
Copier
Modifier
OPENAI_API_KEY="sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
⚠️ Ne pas versionner ce fichier. .env est déjà listé dans .gitignore.

4. Installer les dépendances
📦 PHP (backend)
bash
Copier
Modifier
composer install
🐍 Python (IA)
bash
Copier
Modifier
pip install -r requirements.txt
5. Générer les embeddings IA
À faire une seule fois (génère l’empreinte vectorielle de la base de connaissances) :

bash
Copier
Modifier
php populate_embeddings.php
6. Lancer les services
Lancer Apache et MySQL via XAMPP

Démarrer le microservice IA dans un terminal :

bash
Copier
Modifier
python embedding_server.py
Accéder à l'application via :

arduino
Copier
Modifier
http://localhost/chatbtot2BD/
🧠 Améliorations possibles
⚡ Base vectorielle dédiée : Migrer vers ChromaDB, Weaviate ou Qdrant pour une recherche sémantique plus rapide.

📶 Streaming de réponses : Implémenter un affichage mot-à-mot des réponses IA pour plus de fluidité.

🧩 Agent intelligent (Function Calling) : Permettre au chatbot d’effectuer des actions comme l’inscription à un événement ou l’envoi d’email.

🛡️ Sécurité
Ce dépôt a été nettoyé avec BFG Repo-Cleaner pour supprimer des secrets accidentellement commités (.env).

Si vous utilisez ce projet, assurez-vous de :

Ne jamais exposer vos clés API publiquement.

Ajouter .env, /vendor et autres fichiers sensibles à .gitignore.

📄 Licence
Projet académique.

