<?php
// generate_pdf.php
require_once __DIR__ . '/inc/config.php';

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("Dompdf not installed. Run 'composer require dompdf/dompdf' in project folder.");
}
require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;

$type = $_GET['type'] ?? '';
if ($type === 'vote') {
    $code = $_GET['code'] ?? '';
    $stmt = $pdo->prepare("
        SELECT v.*, u.full_name, c.name AS candidate_name, e.title
        FROM votes v
        LEFT JOIN users u ON v.voter_user_id = u.user_id
        LEFT JOIN candidates c ON v.candidate_id = c.candidate_id
        LEFT JOIN elections e ON v.election_id = e.election_id
        WHERE v.receipt_code = ?
    ");
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        die("Receipt not found.");
    }

    $html = '<div style="font-family: Arial, Helvetica, sans-serif;">';
    $html .= '<h2 style="color:#1E3A8A;">Bestlink College</h2>';
    $html .= '<h3>Vote Receipt</h3>';
    $html .= "<p><strong>Receipt Code:</strong> " . htmlspecialchars($row['receipt_code']) . "</p>";
    $html .= "<p><strong>Election:</strong> " . htmlspecialchars($row['title']) . "</p>";
    $html .= "<p><strong>Voter:</strong> " . htmlspecialchars($row['full_name']) . "</p>";
    $html .= "<p><strong>Candidate:</strong> " . htmlspecialchars($row['candidate_name']) . "</p>";
    $html .= "<p><strong>Time:</strong> " . htmlspecialchars($row['timestamp']) . "</p>";
    $html .= '</div>';

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("vote_receipt_" . preg_replace('/[^A-Za-z0-9_\-]/','', $row['receipt_code']) . ".pdf");
    exit;
}

echo 'Invalid request.';
