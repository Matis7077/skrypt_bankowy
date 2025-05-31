<?php
session_start();

class DatabaseConfig {
    const HOST = 'localhost';
    const USERNAME = 'root';
    const PASSWORD = '';
    const DATABASE = 'kantor_db';
}

// Interfejs dla operacji kantorowych
interface KantorInterface {
    public function przelicz($kwota, $waluta);
    public function zapiszTransakcje($transakcja);
    public function pobierzHistorie($limit = 10);
}

class Database {
    private $connection;
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DatabaseConfig::HOST . ";dbname=" . DatabaseConfig::DATABASE,
                DatabaseConfig::USERNAME,
                DatabaseConfig::PASSWORD
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Błąd połączenia z bazą danych: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

class Kantor implements KantorInterface {
    private $kursy;
    private $database;
    
    public function __construct() {
        $this->kursy = [
            'USD' => 4.25,
            'EUR' => 4.65,
            'GBP' => 5.35,
            'CHF' => 4.85
        ];
        
        $this->database = new Database();
    }
    
    public function przelicz($kwota, $waluta) {
        if (!isset($this->kursy[$waluta])) {
            return false;
        }
        
        $kurs = $this->kursy[$waluta];
        $wynik = $kwota / $kurs;
        
        $transakcja = [
            'kwota_pln' => $kwota,
            'waluta' => $waluta,
            'wynik' => number_format($wynik, 2),
            'kurs' => $kurs
        ];
        
        // Zapisz do bazy danych
        $this->zapiszTransakcje($transakcja);
        
        return $transakcja;
    }
    
    public function zapiszTransakcje($transakcja) {
        try {
            $sql = "INSERT INTO transakcje (kwota_pln, waluta, wynik, kurs) VALUES (?, ?, ?, ?)";
            $stmt = $this->database->getConnection()->prepare($sql);
            $stmt->execute([
                $transakcja['kwota_pln'],
                $transakcja['waluta'],
                $transakcja['wynik'],
                $transakcja['kurs']
            ]);
        } catch(PDOException $e) {
            echo "Błąd zapisu: " . $e->getMessage();
        }
    }
    
    public function pobierzHistorie($limit = 10) {
        try {
            $sql = "SELECT * FROM transakcje ORDER BY data_utworzenia DESC LIMIT ?";
            $stmt = $this->database->getConnection()->prepare($sql);
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo "Błąd odczytu: " . $e->getMessage();
            return [];
        }
    }
    
    public function pobierzKursy() {
        return $this->kursy;
    }
}

$kantor = new Kantor();
$wynik = '';

// Obsługa formularza
if ($_POST) {
    $kwota_pln = $_POST['kwota_pln'];
    $wybrana_waluta = $_POST['waluta'];
    
    $transakcja = $kantor->przelicz($kwota_pln, $wybrana_waluta);
    
    if ($transakcja) {
        $wynik = $transakcja['kwota_pln'] . " PLN = " . $transakcja['wynik'] . " " . $transakcja['waluta'];
    }
}

$kursy = $kantor->pobierzKursy();
$historia = $kantor->pobierzHistorie(5); 
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kantor Walutowy</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #34495e;
        }
        
        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        input[type="text"]:focus, input[type="number"]:focus, select:focus {
            border-color: #3498db;
            outline: none;
        }
        
        button {
            background-color: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            width: 100%;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .wynik {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .historia {
            background-color: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border: 1px solid #b3d9ff;
        }
        
        .transakcja {
            background-color: white;
            padding: 8px;
            margin: 5px 0;
            border-radius: 3px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💰 Kantor Walutowy + MySQL</h1>
 
        <form method="POST">
            <div class="form-group">
                <label for="kwota_pln">Kwota w PLN:</label>
                <input type="number" 
                       id="kwota_pln" 
                       name="kwota_pln" 
                       step="0.01" 
                       placeholder="Wprowadź kwotę w złotówkach"
                       value="<?php echo $_POST['kwota_pln'] ?? ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="waluta">Wybierz walutę:</label>
                <select id="waluta" name="waluta">
                    <option value="">-- Wybierz walutę --</option>
                    <?php foreach ($kursy as $kod => $kurs): ?>
                        <option value="<?php echo $kod; ?>" 
                                <?php echo (($_POST['waluta'] ?? '') === $kod) ? 'selected' : ''; ?>>
                            <?php echo $kod; ?> (1 <?php echo $kod; ?> = <?php echo $kurs; ?> PLN)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit">💱 Przelicz walutę</button>
        </form>
        
        <?php if ($wynik): ?>
            <div class="wynik">
                <strong>Wynik przeliczenia:</strong><br>
                <?php echo $wynik; ?>
            </div>
        <?php endif; ?>
        
        <div id="wynik-na-zywo" class="wynik" style="display: none;">
            <strong>Przeliczenie na żywo:</strong><br>
            <span id="tekst-wyniku"></span>
        </div>
        
        <!-- Historia transakcji -->
        <?php if (!empty($historia)): ?>
            <div class="historia">
                <h3>💾 Historia transakcji z bazy danych:</h3>
                <?php foreach ($historia as $trans): ?>
                    <div class="transakcja">
                        ID: <?php echo $trans['id']; ?> | 
                        <?php echo date('H:i:s', strtotime($trans['data_utworzenia'])); ?> - 
                        <?php echo $trans['kwota_pln']; ?> PLN = 
                        <?php echo $trans['wynik']; ?> <?php echo $trans['waluta']; ?>
                        (kurs: <?php echo $trans['kurs']; ?>)
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const kursy = {
            'USD': 4.25,
            'EUR': 4.65,
            'GBP': 5.35,
            'CHF': 4.85
        };

        function przeliczNaZywo() {
            const kwota = document.getElementById('kwota_pln').value;
            const waluta = document.getElementById('waluta').value;
            const divWynik = document.getElementById('wynik-na-zywo');
            const tekstWyniku = document.getElementById('tekst-wyniku');

            // Sprawdź czy są wprowadzone dane
            if (kwota && waluta && kwota > 0) {
                const kurs = kursy[waluta];
                const kwotaWymiany = (kwota / kurs).toFixed(2);
                
                tekstWyniku.innerHTML = kwota + ' PLN = ' + kwotaWymiany + ' ' + waluta;
                divWynik.style.display = 'block';
            } else {
                divWynik.style.display = 'none';
            }
        }

        document.getElementById('kwota_pln').addEventListener('input', przeliczNaZywo);
        document.getElementById('waluta').addEventListener('change', przeliczNaZywo);
    </script>
</body>
</html>