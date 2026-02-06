<?php
session_start();
require_once '../../includes/db.php';

/* =========================
   SÉCURITÉ ADMIN
========================= */
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

/* =========================
   ID ÉVÉNEMENT
========================= */
$event_id = $_GET['id'] ?? null;
if (!$event_id) {
    header("Location: evenements.php");
    exit;
}

/* =========================
   VÉRIFIER EXISTENCE
========================= */
$stmt = $pdo->prepare(
    "SELECT id_evenement FROM evenements WHERE id_evenement = ?"
);
$stmt->execute([$event_id]);

if (!$stmt->fetch()) {
    header("Location: evenements.php?error=notfound");
    exit;
}

try {
    $pdo->beginTransaction();

    /* =========================
       GALERIE + FICHIERS
    ========================= */
    $stmt = $pdo->prepare(
        "SELECT file_path FROM galerie WHERE event_id = ?"
    );
    $stmt->execute([$event_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $media) {
        $file = "../uploads/" . $media['file_path'];
        if (file_exists($file)) {
            unlink($file);
        }
    }

    $pdo->prepare(
        "DELETE FROM galerie WHERE event_id = ?"
    )->execute([$event_id]);

    /* =========================
       COMMENTAIRES
    ========================= */
    $pdo->prepare(
        "DELETE FROM commentaires WHERE event_id = ?"
    )->execute([$event_id]);

    /* =========================
       PARTICIPANTS
    ========================= */
    $pdo->prepare(
        "DELETE FROM participants_evenements WHERE event_id = ?"
    )->execute([$event_id]);

    /* =========================
       ARTISTES + PHOTOS
    ========================= */
    $stmt = $pdo->prepare(
        "SELECT photo FROM evenement_artistes WHERE id_evenement = ?"
    );
    $stmt->execute([$event_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $artist) {
        if (!empty($artist['photo'])) {
            $photo = "../uploads/artistes/" . $artist['photo'];
            if (file_exists($photo)) {
                unlink($photo);
            }
        }
    }

    $pdo->prepare(
        "DELETE FROM evenement_artistes WHERE id_evenement = ?"
    )->execute([$event_id]);

    /* =========================
       ÉVÉNEMENT
    ========================= */
    $pdo->prepare(
        "DELETE FROM evenements WHERE id_evenement = ?"
    )->execute([$event_id]);

    $pdo->commit();

    header("Location: evenements.php?deleted=1");
    exit;

} catch (PDOException $e) {

    $pdo->rollBack();

    // 🔥 Debug temporaire (à enlever après test)
    die("ERREUR SUPPRESSION : " . $e->getMessage());
}
