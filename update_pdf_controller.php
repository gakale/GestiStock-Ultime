<?php

// Script pour mettre à jour automatiquement le PdfGenerationController
$controllerPath = __DIR__ . '/app/Http/Controllers/PdfGenerationController.php';
$content = file_get_contents($controllerPath);

// Définir le motif de recherche pour les tableaux $companyDetails
$pattern = '/\s+\/\/ Informations de l\'entreprise(?:.*?\n)+?\s+\$companyDetails = \[\s+\'name\' => .*?\n.*?\n.*?\n.*?\n.*?\n.*?\n.*?\];/s';

// Définir le texte de remplacement
$replacement = "\n                // Récupérer les informations de l'entreprise depuis les données du tenant\n                \$companyDetails = \$this->getCompanyDetails();";

// Effectuer le remplacement
$newContent = preg_replace($pattern, $replacement, $content);

// Sauvegarder le fichier mis à jour
file_put_contents($controllerPath, $newContent);

echo "Le fichier PdfGenerationController.php a été mis à jour avec succès.\n";
