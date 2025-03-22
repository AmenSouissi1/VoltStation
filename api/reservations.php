<?php
require_once '../config.php';

// Définir les en-têtes de réponse
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Récupérer la méthode de la requête
$method = $_SERVER['REQUEST_METHOD'];

// Récupérer les parties de l'endpoint
$id = isset($_GET['id']) ? $_GET['id'] : null;
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$borne_id = isset($_GET['borne_id']) ? $_GET['borne_id'] : null;

// Connexion à la base de données
try {
    $db = connectDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Échec de connexion à la base de données']);
    exit;
}

// Traitement de l'endpoint des réservations
if ($method === 'GET') {
    // Interroger la collection MongoDB pour les réservations de sessions
    try {
        $reservationsCollection = $db->reservations_session;
        $stationsCollection = $db->stations;
        $bornesCollection = $db->bornes;
        
        $query = [];

        // Si un ID est fourni, retourner la réservation spécifique
        if ($id) {
            $query['numero'] = $id;
            $reservation = $reservationsCollection->findOne($query);
            
            if ($reservation) {
                // Convertir les ObjectId MongoDB en chaînes pour JSON
                $reservation['_id'] = (string)$reservation['_id'];
                $reservation['utilisateur_id'] = (string)$reservation['utilisateur_id'];
                $reservation['station_id'] = (string)$reservation['station_id'];
                $reservation['borne_id'] = (string)$reservation['borne_id'];
                
                // Get station and borne details
                $station = $stationsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($reservation['station_id'])]);
                $borne = $bornesCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($reservation['borne_id'])]);
                
                // Formater la réponse pour correspondre à la structure attendue
                $formattedReservation = [
                    'id' => $reservation['numero'],
                    'utilisateur' => (string)$reservation['utilisateur_id'],
                    'station' => $station ? $station['nom'] : (string)$reservation['station_id'],
                    'borne' => $borne ? $borne['numero'] : (string)$reservation['borne_id'],
                    'date' => $reservation['date'],
                    'heure_debut' => $reservation['heure_debut'],
                    'heure_fin' => $reservation['heure_fin'],
                    'statut' => $reservation['statut'],
                    'date_reservation' => $reservation['date_reservation']
                ];
                
                if (isset($reservation['date_annulation'])) {
                    $formattedReservation['date_annulation'] = $reservation['date_annulation'];
                }
                
                echo json_encode($formattedReservation);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Réservation non trouvée']);
            }
        } 
        // If user_id is provided, return user's reservations
        elseif ($user_id) {
            // For MongoDB ObjectId
            $mongoId = null;
            try {
                $mongoId = new MongoDB\BSON\ObjectId($user_id);
            } catch (Exception $e) {
                // If not valid MongoDB ObjectId, try string comparison
                $query['utilisateur_id'] = $user_id;
            }
            
            if ($mongoId) {
                $query['utilisateur_id'] = $mongoId;
            }
            
            $cursor = $reservationsCollection->find($query);
            $userReservations = [];
            
            foreach ($cursor as $reservation) {
                // Convert MongoDB ObjectId to string for JSON
                $reservation['_id'] = (string)$reservation['_id'];
                $reservation['utilisateur_id'] = (string)$reservation['utilisateur_id'];
                $reservation['station_id'] = (string)$reservation['station_id'];
                $reservation['borne_id'] = (string)$reservation['borne_id'];
                
                // Get station and borne details
                $station = $stationsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($reservation['station_id'])]);
                $borne = $bornesCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($reservation['borne_id'])]);
                
                // Format response to match expected structure
                $userReservations[] = [
                    'id' => $reservation['numero'],
                    'utilisateur' => (string)$reservation['utilisateur_id'],
                    'station' => $station ? $station['nom'] : (string)$reservation['station_id'],
                    'borne' => $borne ? $borne['numero'] : (string)$reservation['borne_id'],
                    'date' => $reservation['date'],
                    'heure_debut' => $reservation['heure_debut'],
                    'heure_fin' => $reservation['heure_fin'],
                    'statut' => $reservation['statut'],
                    'date_reservation' => $reservation['date_reservation'],
                    'date_annulation' => isset($reservation['date_annulation']) ? $reservation['date_annulation'] : null
                ];
            }
            
            echo json_encode($userReservations);
        }
        // If borne_id is provided, check borne's reservations
        elseif ($borne_id) {
            // For MongoDB ObjectId
            $mongoId = null;
            try {
                $mongoId = new MongoDB\BSON\ObjectId($borne_id);
            } catch (Exception $e) {
                // If not valid MongoDB ObjectId, try string comparison
                $query['borne_id'] = $borne_id;
            }
            
            if ($mongoId) {
                $query['borne_id'] = $mongoId;
            }
            
            $cursor = $reservationsCollection->find($query);
            $borneReservations = [];
            
            foreach ($cursor as $reservation) {
                // Convert MongoDB ObjectId to string for JSON
                $reservation['_id'] = (string)$reservation['_id'];
                $reservation['utilisateur_id'] = (string)$reservation['utilisateur_id'];
                $reservation['station_id'] = (string)$reservation['station_id'];
                $reservation['borne_id'] = (string)$reservation['borne_id'];
                
                // Format response to match expected structure
                $borneReservations[] = [
                    'id' => $reservation['numero'],
                    'utilisateur' => (string)$reservation['utilisateur_id'],
                    'station' => (string)$reservation['station_id'],
                    'borne' => (string)$reservation['borne_id'],
                    'date' => $reservation['date'],
                    'heure_debut' => $reservation['heure_debut'],
                    'heure_fin' => $reservation['heure_fin'],
                    'statut' => $reservation['statut'],
                    'date_reservation' => $reservation['date_reservation'],
                    'date_annulation' => isset($reservation['date_annulation']) ? $reservation['date_annulation'] : null
                ];
            }
            
            echo json_encode($borneReservations);
        }
        // Otherwise return all reservations
        else {
            $cursor = $reservationsCollection->find();
            $allReservations = [];
            
            foreach ($cursor as $reservation) {
                // Convert MongoDB ObjectId to string for JSON
                $reservation['_id'] = (string)$reservation['_id'];
                $reservation['utilisateur_id'] = (string)$reservation['utilisateur_id'];
                $reservation['station_id'] = (string)$reservation['station_id'];
                $reservation['borne_id'] = (string)$reservation['borne_id'];
                
                // Format response to match expected structure
                $allReservations[] = [
                    'id' => $reservation['numero'],
                    'utilisateur' => (string)$reservation['utilisateur_id'],
                    'station' => (string)$reservation['station_id'],
                    'borne' => (string)$reservation['borne_id'],
                    'date' => $reservation['date'],
                    'heure_debut' => $reservation['heure_debut'],
                    'heure_fin' => $reservation['heure_fin'],
                    'statut' => $reservation['statut'],
                    'date_reservation' => $reservation['date_reservation'],
                    'date_annulation' => isset($reservation['date_annulation']) ? $reservation['date_annulation'] : null
                ];
            }
            
            echo json_encode($allReservations);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
} elseif ($method === 'POST') {
    try {
        // Create a new reservation in MongoDB
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit;
        }
        
        // Validate required fields
        if (!isset($data['utilisateur']) || !isset($data['station']) || !isset($data['borne']) || 
            !isset($data['date']) || !isset($data['heure_debut']) || !isset($data['heure_fin'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        // Vérifier que la date et l'heure de réservation ne sont pas dans le passé
        $reservationDateTime = new DateTime($data['date'] . ' ' . $data['heure_debut']);
        $now = new DateTime();
        $now->modify('+30 minutes'); // Ajouter une marge de 30 minutes pour la préparation
        
        if ($reservationDateTime < $now) {
            http_response_code(400);
            echo json_encode(['error' => 'La date et l\'heure de réservation ne peuvent pas être dans le passé']);
            exit;
        }
        
        // Log the received data for debugging
        error_log("API received data: " . json_encode($data));
        
        $reservationsCollection = $db->reservations_session;
        $bornesCollection = $db->bornes;
        
        // Check if the time slot is available
        try {
            // Convert borne ID to MongoDB ObjectId
            $borneObjectId = null;
            try {
                $borneObjectId = new MongoDB\BSON\ObjectId($data['borne']);
            } catch (Exception $e) {
                // If it's not a valid MongoDB ObjectId, try to find the borne by its number
                $borne = $bornesCollection->findOne(['numero' => $data['borne']]);
                if ($borne) {
                    $borneObjectId = $borne['_id'];
                } else {
                    // It might be a borne identifier from the demo data in stations.php
                    // Let's use it as a string ID
                    $borneObjectId = $data['borne'];
                    error_log("Using string borne ID: " . $data['borne']);
                }
            }
            
            if (!$borneObjectId) {
                throw new Exception("Could not resolve borne ID: " . $data['borne']);
            }
            
            // Check if the borne ID is actually an ObjectId or a string
            $query = [
                'date' => $data['date'],
                'statut' => 'confirmé',
                '$or' => [
                    // Overlapping time slots
                    [
                        'heure_debut' => ['$lte' => $data['heure_debut']],
                        'heure_fin' => ['$gt' => $data['heure_debut']]
                    ],
                    [
                        'heure_debut' => ['$lt' => $data['heure_fin']],
                        'heure_fin' => ['$gte' => $data['heure_fin']]
                    ],
                    [
                        'heure_debut' => ['$gte' => $data['heure_debut']],
                        'heure_fin' => ['$lte' => $data['heure_fin']]
                    ]
                ]
            ];
            
            // Add borne_id to the query
            $query['borne_id'] = $borneObjectId;
            
            $existingReservation = $reservationsCollection->findOne($query);
            error_log("Checking for existing reservations with query: " . json_encode($query));
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Error checking time slot: ' . $e->getMessage()]);
            exit;
        }
        
        if ($existingReservation) {
            http_response_code(409); // Conflict
            echo json_encode(['error' => 'Time slot is already reserved']);
            exit;
        }
        
        // Generate reservation number
        $lastReservation = $reservationsCollection->findOne(
            [],
            [
                'sort' => ['numero' => -1],
                'projection' => ['numero' => 1]
            ]
        );
        
        $newNumber = 'R5001';
        if ($lastReservation && isset($lastReservation['numero'])) {
            $lastNumber = (int)substr($lastReservation['numero'], 1);
            $newNumber = 'R' . ($lastNumber + 1);
        }
        
        // Convert user ID to ObjectId if it's a valid MongoDB ID
        $userId = $data['utilisateur'];
        try {
            $userId = new MongoDB\BSON\ObjectId($data['utilisateur']);
        } catch (Exception $e) {
            error_log("Warning: Invalid user ID format: " . $e->getMessage());
            // Keep it as is if it's not a valid ObjectId
        }
        
        // Convert station ID to ObjectId
        $stationObjectId = null;
        try {
            $stationObjectId = new MongoDB\BSON\ObjectId($data['station']);
        } catch (Exception $e) {
            // If it's not a valid MongoDB ObjectId, try to find the station by its name
            $station = $db->stations->findOne(['nom' => $data['station']]);
            if ($station) {
                $stationObjectId = $station['_id'];
            } else {
                // Log error but continue with the original ID as fallback
                error_log("Warning: Could not resolve station ID: " . $data['station']);
                $stationObjectId = $data['station'];
            }
        }
        
        // Create reservation document
        $newReservation = [
            'utilisateur_id' => $userId,
            'station_id' => $stationObjectId,
            'borne_id' => $borneObjectId, // Already resolved above
            'date' => $data['date'],
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'],
            'statut' => 'confirmé',
            'date_reservation' => date('Y-m-d H:i:s'),
            'numero' => $newNumber
        ];
        
        // Log the document to be inserted
        error_log("New reservation document: " . json_encode($newReservation));
        
        // Insert the reservation
        $result = $reservationsCollection->insertOne($newReservation);
        
        if ($result->getInsertedCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Reservation created successfully',
                'id' => $newNumber
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create reservation']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
} elseif ($method === 'PUT') {
    try {
        // Update a reservation in MongoDB
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Reservation ID is required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit;
        }
        
        $reservationsCollection = $db->reservations_session;
        
        // Create update document
        $updateData = [];
        
        // Only include fields that are being updated
        if (isset($data['statut'])) {
            $updateData['statut'] = $data['statut'];
        }
        
        if (isset($data['date'])) {
            $updateData['date'] = $data['date'];
        }
        
        if (isset($data['heure_debut'])) {
            $updateData['heure_debut'] = $data['heure_debut'];
        }
        
        if (isset($data['heure_fin'])) {
            $updateData['heure_fin'] = $data['heure_fin'];
        }
        
        if (isset($data['borne'])) {
            $updateData['borne_id'] = new MongoDB\BSON\ObjectId($data['borne']);
        }
        
        // If status is changed to cancelled, add cancellation date
        if (isset($data['statut']) && $data['statut'] === 'annulé') {
            $updateData['date_annulation'] = date('Y-m-d H:i:s');
        }
        
        // If no fields to update, return error
        if (empty($updateData)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        
        // Update the reservation
        $result = $reservationsCollection->updateOne(
            ['numero' => $id],
            ['$set' => $updateData]
        );
        
        if ($result->getModifiedCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Reservation updated successfully',
                'id' => $id
            ]);
        } else {
            if ($result->getMatchedCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'No changes made to reservation',
                    'id' => $id
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Reservation not found']);
            }
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
} elseif ($method === 'DELETE') {
    try {
        // Cancel a reservation in MongoDB
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Reservation ID is required']);
            exit;
        }
        
        $reservationsCollection = $db->reservations_session;
        
        // Update reservation status to cancelled and add cancellation timestamp
        $result = $reservationsCollection->updateOne(
            ['numero' => $id],
            [
                '$set' => [
                    'statut' => 'annulé',
                    'date_annulation' => date('Y-m-d H:i:s')
                ]
            ]
        );
        
        if ($result->getModifiedCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Reservation cancelled successfully',
                'id' => $id
            ]);
        } else {
            if ($result->getMatchedCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Reservation was already cancelled',
                    'id' => $id
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Reservation not found']);
            }
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}