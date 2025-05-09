<?php
require_once 'connexion.php';
$pdo = getDBConnection(); // Connexion sécurisée

session_start();

if (!isset($_GET['id'])) {
    die("ID de livre manquant.");
}

$id_livre = intval($_GET['id']);
$id_utilisateur = $_SESSION['id'] ?? 1; // Temporairement 1 si session non définie

try {
    // Vérifie si le livre existe et a du stock
    $stmt = $pdo->prepare("SELECT quantite FROM livres WHERE id_livre = ?");
    $stmt->execute([$id_livre]);
    $livre = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$livre) {
        die("Livre introuvable.");
    }

    if ($livre['quantite'] <= 0) {
        echo "<script>alert('Ce livre est actuellement indisponible.'); window.location.href = '../hajar/liste_livres.php';</script>";
        exit;
    }

    // Vérifier s’il a déjà réservé ce livre
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE id_utilisateur = ? AND id_livre = ?");
    $stmt->execute([$id_utilisateur, $id_livre]);
    $dejaReserve = $stmt->fetchColumn();

    if ($dejaReserve > 0) {
        header("Location: ../hajar/liste_livres.php?error=deja_reserve");
        exit;
    }

    // Vérifier s’il a déjà 2 réservations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE id_utilisateur = ?");
    $stmt->execute([$id_utilisateur]);
    $reservationsEnCours = $stmt->fetchColumn();

    if ($reservationsEnCours >= 2) {
        header("Location: ../hajar/liste_livres.php?error=max_reservations");
        exit;
    }

    // **Sécurisation avec une transaction**
    $pdo->beginTransaction();

    // Insère la réservation
    $stmt = $pdo->prepare("INSERT INTO reservations (id_utilisateur, id_livre, date_reservation, etat) VALUES (?, ?, NOW(), 'en attente')");
    $stmt->execute([$id_utilisateur, $id_livre]);

    // **Assurer que la mise à jour du stock se fait une seule fois**
    $stmt = $pdo->prepare("UPDATE livres SET quantite = quantite - 1 WHERE id_livre = ? AND quantite > 0");
    $stmt->execute([$id_livre]);

    $pdo->commit(); // Valide la transaction

    // Redirection avec message
    echo "<script>alert('✅ Réservation effectuée avec succès !'); window.location.href = '../hajar/liste_livres.php';</script>";
} catch (PDOException $e) {
    $pdo->rollBack(); // Annuler la transaction en cas d'erreur
    die("❌ Erreur : " . htmlspecialchars($e->getMessage()));
}

exit;
