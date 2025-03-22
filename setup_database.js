const { MongoClient } = require('mongodb');
const bcrypt = require('bcryptjs');

// Connection URI
const uri = 'mongodb://localhost:27017';
const client = new MongoClient(uri);
const dbName = 'voltstation';

// Function to hash password
async function hashPassword(password) {
    const saltRounds = 10;
    return await bcrypt.hash(password, saltRounds);
}

async function main() {
    try {
        // Connect to MongoDB
        await client.connect();
        console.log('Connected to MongoDB server');
        
        const db = client.db(dbName);
        
        // Drop existing collections (if they exist) to start fresh
        const collections = [
            'stations', 
            'bornes', 
            'utilisateurs', 
            'sessions', 
            'tarifications', 
            'reservations_session',
            'abonnement_transactions'  // Add new collection for subscription transactions
        ];
        
        for (const collection of collections) {
            try {
                await db.collection(collection).drop();
                console.log(`Collection ${collection} dropped`);
            } catch (err) {
                console.log(`Collection ${collection} does not exist yet`);
            }
        }
        
        // Create stations collection with sample data
        const stations = [
            { 
                nom: 'Station Paris Centre', 
                adresse: '15 Rue de Rivoli, 75004 Paris',
                coordonnees: { lat: 48.856614, lng: 2.352222 },
                heures_ouverture: '24/7',
                statut: 'actif',
                date_creation: new Date('2023-01-15')
            },
            { 
                nom: 'Station Lyon Part-Dieu', 
                adresse: '5 Place Charles Béraudier, 69003 Lyon',
                coordonnees: { lat: 45.760252, lng: 4.859562 },
                heures_ouverture: '6h-22h',
                statut: 'actif',
                date_creation: new Date('2023-02-10')
            },
            { 
                nom: 'Station Marseille Prado', 
                adresse: '45 Boulevard Michelet, 13008 Marseille',
                coordonnees: { lat: 43.267769, lng: 5.396972 },
                heures_ouverture: '7h-21h',
                statut: 'maintenance',
                date_creation: new Date('2023-03-05')
            }
        ];
        
        await db.collection('stations').insertMany(stations);
        console.log('Stations inserted');
        
        // Create bornes collection with sample data
        const bornes = [
            {
                station_id: (await db.collection('stations').findOne({ nom: 'Station Paris Centre' }))._id,
                numero: 'B001',
                type: 'Rapide',
                puissance: 50,
                connecteur: 'CCS',
                statut: 'disponible',
                date_installation: new Date('2023-01-20')
            },
            {
                station_id: (await db.collection('stations').findOne({ nom: 'Station Paris Centre' }))._id,
                numero: 'B002',
                type: 'Standard',
                puissance: 22,
                connecteur: 'Type 2',
                statut: 'disponible',
                date_installation: new Date('2023-01-20')
            },
            {
                station_id: (await db.collection('stations').findOne({ nom: 'Station Lyon Part-Dieu' }))._id,
                numero: 'B001',
                type: 'Rapide',
                puissance: 50,
                connecteur: 'CCS',
                statut: 'en charge',
                date_installation: new Date('2023-02-15')
            },
            {
                station_id: (await db.collection('stations').findOne({ nom: 'Station Marseille Prado' }))._id,
                numero: 'B001',
                type: 'Ultra-rapide',
                puissance: 150,
                connecteur: 'CCS',
                statut: 'hors service',
                date_installation: new Date('2023-03-10')
            }
        ];
        
        await db.collection('bornes').insertMany(bornes);
        console.log('Bornes inserted');
        
        // Create utilisateurs collection with sample data - hash passwords dynamically
        // First define user data without passwords
        const utilisateursData = [
            {
                nom: 'Dupont',
                prenom: 'Jean',
                email: 'jean.dupont@email.com',
                telephone: '0612345678',
                role: 'admin',
                date_inscription: new Date('2023-01-01'),
                vehicule: {
                    marque: 'Renault',
                    modele: 'Zoe',
                    annee: 2021,
                    immatriculation: 'AB-123-CD'
                }
            },
            {
                nom: 'Martin',
                prenom: 'Sophie',
                email: 'sophie.martin@email.com',
                telephone: '0698765432',
                role: 'utilisateur',
                date_inscription: new Date('2023-01-15'),
                vehicule: {
                    marque: 'Tesla',
                    modele: 'Model 3',
                    annee: 2022,
                    immatriculation: 'EF-456-GH'
                }
            },
            {
                nom: 'Petit',
                prenom: 'Thomas',
                email: 'thomas.petit@email.com',
                telephone: '0676543210',
                role: 'utilisateur',
                date_inscription: new Date('2023-02-10'),
                vehicule: {
                    marque: 'Peugeot',
                    modele: 'e-208',
                    annee: 2020,
                    immatriculation: 'IJ-789-KL'
                }
            }
        ];
        
        // Now create users with hashed passwords and default subscription
        const defaultPassword = 'Password123';
        const hashedPassword = await hashPassword(defaultPassword);
        console.log(`Generated hash for default password (Password123): ${hashedPassword}`);
        
        // Add the hashed password and subscription to each user
        const today = new Date();
        const subscriptionEndDate = new Date(today);
        subscriptionEndDate.setDate(today.getDate() + 30);
        
        const utilisateurs = utilisateursData.map(user => ({
            ...user,
            password: hashedPassword,
            abonnement: {
                type: 'standard',
                nom: 'Standard',
                prix: 19.99,
                date_debut: today,
                date_fin: subscriptionEndDate,
                statut: 'actif'
            }
        }));
        
        await db.collection('utilisateurs').insertMany(utilisateurs);
        console.log('Utilisateurs inserted with dynamically hashed passwords');
        
        // Create sessions collection with sample data
        const sessions = [
            {
                utilisateur_id: (await db.collection('utilisateurs').findOne({ email: 'sophie.martin@email.com' }))._id,
                borne_id: (await db.collection('bornes').findOne({ numero: 'B001', type: 'Rapide' }))._id,
                date_debut: new Date('2023-03-01T14:30:00'),
                date_fin: new Date('2023-03-01T15:15:00'),
                energie_consommee: 25.5,
                cout: 12.75,
                statut: 'terminée'
            },
            {
                utilisateur_id: (await db.collection('utilisateurs').findOne({ email: 'thomas.petit@email.com' }))._id,
                borne_id: (await db.collection('bornes').findOne({ numero: 'B002' }))._id,
                date_debut: new Date('2023-03-05T09:45:00'),
                date_fin: new Date('2023-03-05T11:15:00'),
                energie_consommee: 30.2,
                cout: 15.10,
                statut: 'terminée'
            },
            {
                utilisateur_id: (await db.collection('utilisateurs').findOne({ email: 'sophie.martin@email.com' }))._id,
                borne_id: (await db.collection('bornes').findOne({ statut: 'en charge' }))._id,
                date_debut: new Date(),
                date_fin: null,
                energie_consommee: null,
                cout: null,
                statut: 'en cours'
            }
        ];
        
        await db.collection('sessions').insertMany(sessions);
        console.log('Sessions inserted');
        
        // Create tarifications collection with sample data
        const tarifications = [
            {
                type_borne: 'Standard',
                prix_kwh: 0.35,
                prix_minute: 0.05,
                frais_service: 1.0,
                date_effet: new Date('2023-01-01')
            },
            {
                type_borne: 'Rapide',
                prix_kwh: 0.50,
                prix_minute: 0.08,
                frais_service: 1.5,
                date_effet: new Date('2023-01-01')
            },
            {
                type_borne: 'Ultra-rapide',
                prix_kwh: 0.65,
                prix_minute: 0.10,
                frais_service: 2.0,
                date_effet: new Date('2023-01-01')
            }
        ];
        
        await db.collection('tarifications').insertMany(tarifications);
        console.log('Tarifications inserted');
        
        // Create session reservations collection with sample data
        const currentDay = new Date();
        const tomorrow = new Date(currentDay);
        tomorrow.setDate(tomorrow.getDate() + 1);
        const yesterday = new Date(currentDay);
        yesterday.setDate(yesterday.getDate() - 1);
        const nextWeek = new Date(currentDay);
        nextWeek.setDate(nextWeek.getDate() + 7);
        
        const reservations = [
            {
                utilisateur_id: (await db.collection('utilisateurs').findOne({ email: 'sophie.martin@email.com' }))._id,
                station_id: (await db.collection('stations').findOne({ nom: 'Station Paris Centre' }))._id,
                borne_id: (await db.collection('bornes').findOne({ numero: 'B001', type: 'Rapide' }))._id,
                date: yesterday.toISOString().split('T')[0],
                heure_debut: '14:00:00',
                heure_fin: '16:00:00',
                statut: 'confirmé',
                date_reservation: new Date(yesterday.setHours(yesterday.getHours() - 2)).toISOString(),
                numero: 'R5678'
            },
            {
                utilisateur_id: (await db.collection('utilisateurs').findOne({ email: 'thomas.petit@email.com' }))._id,
                station_id: (await db.collection('stations').findOne({ nom: 'Station Lyon Part-Dieu' }))._id,
                borne_id: (await db.collection('bornes').findOne({ numero: 'B001', type: 'Rapide' }))._id,
                date: currentDay.toISOString().split('T')[0],
                heure_debut: '18:00:00',
                heure_fin: '19:30:00',
                statut: 'confirmé',
                date_reservation: new Date(currentDay.setHours(currentDay.getHours() - 5)).toISOString(),
                numero: 'R5679'
            },
            {
                utilisateur_id: (await db.collection('utilisateurs').findOne({ email: 'sophie.martin@email.com' }))._id,
                station_id: (await db.collection('stations').findOne({ nom: 'Station Paris Centre' }))._id,
                borne_id: (await db.collection('bornes').findOne({ numero: 'B002' }))._id,
                date: tomorrow.toISOString().split('T')[0],
                heure_debut: '10:00:00',
                heure_fin: '11:30:00',
                statut: 'confirmé',
                date_reservation: new Date(currentDay).toISOString(),
                numero: 'R5680'
            },
            {
                utilisateur_id: (await db.collection('utilisateurs').findOne({ email: 'jean.dupont@email.com' }))._id,
                station_id: (await db.collection('stations').findOne({ nom: 'Station Marseille Prado' }))._id,
                borne_id: (await db.collection('bornes').findOne({ numero: 'B001', type: 'Ultra-rapide' }))._id,
                date: nextWeek.toISOString().split('T')[0],
                heure_debut: '15:00:00',
                heure_fin: '16:30:00',
                statut: 'confirmé',
                date_reservation: new Date(currentDay).toISOString(),
                numero: 'R5681'
            },
            {
                utilisateur_id: (await db.collection('utilisateurs').findOne({ email: 'thomas.petit@email.com' }))._id,
                station_id: (await db.collection('stations').findOne({ nom: 'Station Lyon Part-Dieu' }))._id,
                borne_id: (await db.collection('bornes').findOne({ numero: 'B001', type: 'Rapide' }))._id,
                date: yesterday.toISOString().split('T')[0],
                heure_debut: '09:00:00',
                heure_fin: '10:30:00',
                statut: 'annulé',
                date_reservation: new Date(yesterday.setDate(yesterday.getDate() - 2)).toISOString(),
                date_annulation: new Date(yesterday.setDate(yesterday.getDate() + 1)).toISOString(),
                numero: 'R5682'
            }
        ];
        
        await db.collection('reservations_session').insertMany(reservations);
        console.log('Session reservations inserted');
        
        // Crée la collection des abonnements si elle n'existe pas
        try {
            await db.collection('abonnements').drop();
            console.log(`Collection abonnements dropped`);
        } catch (err) {
            console.log(`Collection abonnements does not exist yet`);
        }
        
        // Ajouter des types d'abonnements
        const abonnements = [
            {
                id: 'basic',
                nom: 'Basique',
                prix: 9.99,
                description: 'Accès à toutes les stations, pas de réduction sur les tarifs',
                avantages: [
                    'Réservation de sessions',
                    'Accès à l\'historique de consommation',
                    'Support client par email'
                ],
                couleur: 'primary'
            },
            {
                id: 'standard',
                nom: 'Standard',
                prix: 19.99,
                description: 'Accès privilégié et réduction de 10% sur tous les tarifs',
                avantages: [
                    'Réservation de sessions prioritaire',
                    'Réduction de 10% sur tous les tarifs',
                    'Accès à l\'historique détaillé',
                    'Support client par email et téléphone'
                ],
                couleur: 'success',
                recommande: true
            },
            {
                id: 'premium',
                nom: 'Premium',
                prix: 29.99,
                description: 'Service VIP avec réduction de 20% sur tous les tarifs',
                avantages: [
                    'Réservation de sessions prioritaire',
                    'Réduction de 20% sur tous les tarifs',
                    'Statistiques de consommation avancées',
                    'Support client dédié 24/7',
                    'Recharges gratuites (2 par mois)'
                ],
                couleur: 'warning'
            }
        ];
        
        await db.collection('abonnements').insertMany(abonnements);
        console.log('Abonnements inserted');
        
        // Create sample subscription transactions
        console.log('Creating sample subscription transactions...');
        const transactionData = [];
        
        // For each user, create some historical subscription transactions
        const users = await db.collection('utilisateurs').find().toArray();
        const currentDate = new Date(); // use currentDate instead of today to avoid conflict
        
        for (const user of users) {
            const twoMonthsAgo = new Date(currentDate);
            twoMonthsAgo.setMonth(twoMonthsAgo.getMonth() - 2);
            
            const oneMonthAgo = new Date(currentDate);
            oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
            
            transactionData.push({
                utilisateur_id: user._id,
                date: twoMonthsAgo,
                montant: 19.99,
                description: 'Renouvellement abonnement Standard',
                statut: 'Payé'
            });
            
            transactionData.push({
                utilisateur_id: user._id,
                date: oneMonthAgo,
                montant: 19.99,
                description: 'Renouvellement abonnement Standard',
                statut: 'Payé'
            });
        }
        
        await db.collection('abonnement_transactions').insertMany(transactionData);
        console.log('Subscription transactions inserted');
        
        // Verify data insertion by counting documents
        for (const collection of collections) {
            const count = await db.collection(collection).countDocuments();
            console.log(`${collection}: ${count} documents`);
        }
        
        console.log('Database setup completed successfully');
        
    } catch (err) {
        console.error('An error occurred:', err);
    } finally {
        await client.close();
        console.log('MongoDB connection closed');
    }
}

main().catch(console.error);