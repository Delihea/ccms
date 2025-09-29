<?php
include 'layout/header.php';

if (!in_array(current_user_role(), ['Super Admin', 'Club Adviser'])) {
    header('Location: elections.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO elections (title, description, club_id, created_by, 
                          voting_start, voting_end, status) VALUES (?, ?, ?, ?, ?, ?, 'upcoming')");
    $stmt->execute([
        $_POST['title'],
        $_POST['description'],
        $_POST['club_id'],
        current_user_id(),
        $_POST['voting_start'],
        $_POST['voting_end']
    ]);
    $election_id = $pdo->lastInsertId();

    // Positions
    foreach ($_POST['positions'] as $pos) {
        if (!empty($pos['name'])) {
            $stmt = $pdo->prepare("INSERT INTO election_positions (election_id, position_name, max_candidates, description) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$election_id, $pos['name'], $pos['max_candidates'], $pos['description']]);
        }
    }

    $_SESSION['success'] = "Election created!";
    header("Location: elections.php");
    exit;
}

$clubs = $pdo->query("SELECT * FROM clubs ORDER BY club_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Create Election</h2>
    <form method="post">
        <div class="mb-3">
            <label>Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Description</label>
            <textarea name="description" class="form-control"></textarea>
        </div>
        <div class="mb-3">
            <label>Club</label>
            <select name="club_id" class="form-select" required>
                <option value="">Select...</option>
                <?php foreach ($clubs as $c): ?>
                    <option value="<?= $c['club_id'] ?>"><?= htmlspecialchars($c['club_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row">
            <div class="col">
                <label>Voting Start</label>
                <input type="datetime-local" name="voting_start" class="form-control" required>
            </div>
            <div class="col">
                <label>Voting End</label>
                <input type="datetime-local" name="voting_end" class="form-control" required>
            </div>
        </div>

        <h4 class="mt-4">Positions</h4>
        <div id="positions">
            <div class="mb-3">
                <input type="text" name="positions[0][name]" class="form-control mb-1" placeholder="Position Name">
                <input type="number" name="positions[0][max_candidates]" class="form-control mb-1" value="1" min="1">
                <input type="text" name="positions[0][description]" class="form-control mb-1" placeholder="Description">
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-secondary" onclick="addPos()">+ Add Position</button>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">Create</button>
        </div>
    </form>
</div>

<script>
let posCount = 1;
function addPos() {
    const div = document.createElement('div');
    div.classList.add('mb-3');
    div.innerHTML = `
        <input type="text" name="positions[${posCount}][name]" class="form-control mb-1" placeholder="Position Name">
        <input type="number" name="positions[${posCount}][max_candidates]" class="form-control mb-1" value="1" min="1">
        <input type="text" name="positions[${posCount}][description]" class="form-control mb-1" placeholder="Description">
    `;
    document.getElementById('positions').appendChild(div);
    posCount++;
}
</script>

<?php include 'layout/footer.php'; ?>
