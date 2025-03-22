<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$xmlFilePath = 'recettes.xml';
$xml = simplexml_load_file($xmlFilePath);

if (!$xml) {
    die("Erreur : Impossible de charger le fichier XML.");
}

$xpath = new DOMXPath(dom_import_simplexml($xml)->ownerDocument);

$action = $_GET['action'] ?? $_POST['action'] ?? null;








// Fonction pour lire toutes les recettes
if ($action === 'read') {
    $recettes = [];
    foreach ($xml->recette as $recette) {
        $recettes[] = [
            'id' => (int) $recette->id,
            'nom' => (string) $recette->nom,
            'catégorie' => (string) $recette->catégorie,
            'difficulté' => (string) $recette->difficulté,
            'temps_cuisson' => (int) $recette->temps_cuisson,
            'image' => (string) $recette->image,
        ];
    }

    // Retourner les recettes sous forme de JSON
    echo json_encode(['recettes' => $recettes]);
    exit;
}






// Fonction pour ajouter une nouvelle recette
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collecting the form data
    $nom = $_POST['nom'];
    $catégorie = $_POST['catégorie'];
    $difficulté = $_POST['difficulté'];
    $ingrédients = explode("\n", trim($_POST['ingredients']));
    $instructions = explode("\n", trim($_POST['instructions']));
    $temps_cuisson = $_POST['temps_cuisson'];

    // Handle image uploads
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = basename($_FILES['image']['name']);
        $fileDestination = 'images/' . $fileName;
        move_uploaded_file($fileTmpPath, $fileDestination);
        $imagePath = $fileDestination;
    }

    // Find the next unique ID
    $maxId = 0;
    foreach ($xml->recette as $recette) {
        $id = (int) $recette->id;
        if ($id > $maxId) {
            $maxId = $id;
        }
    }
    $newId = $maxId + 1;

    $newRecette = $xml->addChild('recette');
    $newRecette->addChild('id', (string) $newId); 
    $newRecette->addChild('nom', $nom);
    $newRecette->addChild('catégorie', $catégorie);
    $newRecette->addChild('difficulté', $difficulté);

    if ($imagePath) {
        $newRecette->addChild('image', $imagePath);
    }

    $ingredientsNode = $newRecette->addChild('ingrédients');
    foreach ($ingrédients as $ingrédient) {
        if (trim($ingrédient) !== "") {
            $ingredientsNode->addChild('ingrédient', trim($ingrédient));
        }
    }

    $instructionsNode = $newRecette->addChild('instructions');
    foreach ($instructions as $étape) {
        if (trim($étape) !== "") {
            $instructionsNode->addChild('étape', trim($étape));
        }
    }

    $newRecette->addChild('temps_cuisson', $temps_cuisson);

    $xml->asXML($xmlFilePath);

    header("Location: index.html");
    exit;
}











//modification
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nom = $_POST['nom'];
    $catégorie = $_POST['catégorie'];
    $difficulté = $_POST['difficulté'];
    $ingrédients = explode("\n", $_POST['ingredients']);
    $instructions = explode("\n", $_POST['instructions']);
    $temps_cuisson = $_POST['temps_cuisson'];

    $result = $xpath->query("//recette[id = '$id']")->item(0);
    if ($result) {
        $recette = simplexml_import_dom($result);

        $recette->nom = $nom;
        $recette->catégorie = $catégorie;
        $recette->difficulté = $difficulté;

        unset($recette->ingrédients->ingrédient);
        $ingredientsNode = $recette->ingrédients;
        foreach ($ingrédients as $ingrédient) {
            $ingredientsNode->addChild('ingrédient', trim($ingrédient));
        }

        unset($recette->instructions->étape);
        $instructionsNode = $recette->instructions;
        foreach ($instructions as $étape) {
            $instructionsNode->addChild('étape', trim($étape));
        }

        $recette->temps_cuisson = $temps_cuisson;

        $xml->asXML($xmlFilePath);
        header("Location: index.html");
        exit;
    } else {
        echo "Recette introuvable.";
    }
    exit;
}






//supression
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    $result = $xpath->query("//recette[id = '$id']")->item(0);
    if ($result) {
        $domElement = dom_import_simplexml($result);

        $imageNode = $domElement->getElementsByTagName('image')->item(0);
                $imagePath = $imageNode ? $imageNode->nodeValue : null;

        $parentNode = $domElement->parentNode;  
        $parentNode->removeChild($domElement);

        $xml->asXML($xmlFilePath);

        if ($imagePath && file_exists($imagePath)) {
            unlink($imagePath);
        }

        echo "Recette supprimée avec succès.";
        exit;
    } else {
        echo "Recette introuvable.";
    }
}









if ($action === 'get') {
    $id = (int) ($_GET['id'] ?? 0);
    $result = $xpath->query("//recette[id = '$id']")->item(0);

    if ($result) {
        $recette = simplexml_import_dom($result);

        $ingredientsArray = [];
        foreach ($recette->ingrédients->ingrédient as $ingrédient) {
            $ingredientsArray[] = (string) $ingrédient;
        }

        $instructionsArray = [];
        foreach ($recette->instructions->étape as $étape) {
            $instructionsArray[] = (string) $étape;
        }

        $recette_détails = [
            'id' => (int) $recette->id,
            'nom' => (string) $recette->nom,
            'catégorie' => (string) $recette->catégorie,
            'difficulté' => (string) $recette->difficulté,
            'ingrédients' => $ingredientsArray,
            'instructions' => $instructionsArray,
            'temps_cuisson' => (int) $recette->temps_cuisson,
            'image' => (string) $recette->image,
        ];

        echo json_encode(['recette' => $recette_détails]);  
    } else {
        echo json_encode(['erreur' => 'Recette introuvable']);  
    }
    exit;
}


