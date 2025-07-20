<?php
// create_admin.php
// Ce script est à usage unique pour créer un administrateur.

require_once 'api/config.php'; // Pour la connexion à la DB

// --- Paramètres du compte admin à créer ---
$admin_name = "Administrateur Principal";
$admin_email = "admin@iam.edu.sn";
$admin_password = "123456"; // Choisissez un mot de passe temporaire

// On se connecte à la base de données
$conn = connect_db();

// On vérifie si l'email existe déjà pour éviter les doublons
$stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt_check->bind_param("s", $admin_email);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    echo "L'administrateur avec l'email '$admin_email' existe déjà.\n";
    $conn->close();
    exit();
}
$stmt_check->close();

// Hachage sécurisé du mot de passe
$hashed_password = password_hash($admin_password, PASSWORD_BCRYPT);

// Préparation de la requête d'insertion
$stmt_insert = $conn->prepare(
    "INSERT INTO users (name, email, password, user_type) VALUES (?, ?, ?, 'admin')"
);

// On lie les paramètres
$stmt_insert->bind_param("sss", $admin_name, $admin_email, $hashed_password);

// On exécute la requête
if ($stmt_insert->execute()) {
    echo "Succès ! Le compte administrateur a été créé.\n";
    echo "Email: " . $admin_email . "\n";
    echo "Mot de passe: " . $admin_password . "\n";
} else {
    echo "Erreur lors de la création de l'administrateur : " . $stmt_insert->error . "\n";
}

$stmt_insert->close();
$conn->close();
?>