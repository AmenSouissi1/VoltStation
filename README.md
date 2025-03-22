# VoltStation

Système de gestion de bornes de recharge pour véhicules électriques conçu pour simplifier l'exploitation, la surveillance et la gestion des infrastructures de recharge.

## Description du Projet

VoltStation est une plateforme web complète qui aide à gérer les stations de recharge pour véhicules électriques. Le système fournit une surveillance en temps réel des stations de recharge, la gestion des utilisateurs, le traitement des paiements et des analyses pour optimiser les opérations des stations de recharge et améliorer l'expérience utilisateur.

## Fonctionnalités

- **Surveillance en temps réel** : Suivez l'état de toutes les stations de recharge du réseau
- **Système de réservation de sessions** : Permettez aux utilisateurs de réserver des sessions de recharge à l'avance
- **Analyses d'utilisation** : Générez des rapports sur l'utilisation des stations, la consommation d'énergie et les revenus
- **Alertes de maintenance** : Notifications automatisées pour les besoins de maintenance
- **Traitement des paiements** : Gérez les paiements pour les services de recharge
- **Gestion des utilisateurs** : Enregistrez, authentifiez et gérez les profils et les permissions des utilisateurs
- **Tableau de bord administrateur** : Panneau de contrôle complet pour les administrateurs du système
- **Interface responsive** : Accédez au système depuis n'importe quel appareil

## Technologies

- **Frontend** : HTML, CSS, JavaScript
- **Backend** : PHP
- **Base de données** : MongoDB
- **Authentification** : JWT (JSON Web Tokens)
- **Intégration de cartes** : Leaflet.js pour la cartographie des stations
- **Design responsive** : Framework Bootstrap

## Structure du Projet

```
VoltStation/
├── css/                   # Feuilles de style CSS
├── js/                    # Fichiers JavaScript
├── includes/              # Composants PHP réutilisables
├── auth/                  # Gestionnaires d'authentification
├── api/                   # Points de terminaison de l'API REST
├── pages/                 # Pages d'interface
│   ├── admin/             # Pages administrateur
│   └── user/              # Pages utilisateur
├── index.php              # Point d'entrée principal
├── config.php             # Fichier de configuration
└── README.md              # Documentation du projet
```

## Installation

### Prérequis

- PHP 8.1 ou supérieur
- MongoDB 4.4 ou supérieur
- Serveur web (Apache/Nginx)

### Configuration de MongoDB

1. Installez MongoDB Community Edition
2. Démarrez le service MongoDB
3. Créez une nouvelle base de données pour VoltStation :
   ```
   use voltstation
   db.createCollection("stations")
   db.createCollection("bornes")
   db.createCollection("utilisateurs")
   db.createCollection("sessions")
   db.createCollection("tarifications")
   db.createCollection("reservations_session")
   ```

### Configuration du Projet

1. Clonez le dépôt
2. Configurez la connexion à la base de données dans `config.php`
3. Configurez le serveur web pour qu'il pointe vers le répertoire du projet
4. Testez et remplissez la base de données avec ```node setup_databse.js```
5. Accédez à l'application dans votre navigateur

Liste des comptes de test (mot de passe : 'Password123')
sophie.martin@email.com
thomas.petit@email.com
jean.dupont@email.com (admin)
