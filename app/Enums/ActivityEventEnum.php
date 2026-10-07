<?php

namespace App\Enums;

use App\Enums\ActivityCategoryEnum as Category;
use App\Enums\ActivityLevelEnum as Level;
use App\Enums\ActivityStateEnum as State;
use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * Every event the activity journal records. Each one knows its category,
 * how it went (level), its name, and the state it leaves its operation in
 * (null: the operation's state doesn't change, e.g. "client came back").
 */
enum ActivityEventEnum: string implements HasLabel
{
    use EnumHelpers;

    // Payment links
    case PAYMENT_LINK_CREATED = 'payment.link_created';
    case PAYMENT_LINK_EMAILED = 'payment.link_emailed';
    case PAYMENT_LINK_EMAIL_FAILED = 'payment.link_email_failed';
    case PAYMENT_LINK_UNCONFIRMED = 'payment.link_unconfirmed';
    case PAYMENT_LINK_REFUSED = 'payment.link_refused';
    case PAYMENT_LINK_CONFIRMED = 'payment.link_confirmed';
    case PAYMENT_NOT_CREATED = 'payment.not_created';
    case PAYMENT_LINK_CANCELLED = 'payment.link_cancelled';
    case PAYMENT_CLIENT_RETURNED = 'payment.client_returned';
    case PAYMENT_PAID = 'payment.paid';
    case PAYMENT_FAILED = 'payment.failed';
    case PAYMENT_EXPIRED = 'payment.expired';
    case PAYMENT_TOTAL_CHANGED = 'payment.total_changed';

    // Refunds (logged inside their payment's operation)
    case REFUND_REQUESTED = 'refund.requested';
    case REFUND_BLOCKED = 'refund.blocked';
    case REFUND_SUCCEEDED = 'refund.succeeded';
    case REFUND_PENDING = 'refund.pending';
    case REFUND_UNCONFIRMED = 'refund.unconfirmed';
    case REFUND_FAILED = 'refund.failed';
    case REFUND_FOLLOW_UP_STOPPED = 'refund.follow_up_stopped';

    // Google Ads lead forms
    case GOOGLE_ADS_LEAD_CREATED = 'google_ads.lead_created';
    case GOOGLE_ADS_TEST_RECEIVED = 'google_ads.test_received';
    case GOOGLE_ADS_DUPLICATE_IGNORED = 'google_ads.duplicate_ignored';
    case GOOGLE_ADS_REJECTED = 'google_ads.rejected';

    // Logins
    case AUTH_LOGIN = 'auth.login';
    case AUTH_LOGOUT = 'auth.logout';
    case AUTH_PASSWORD_CHANGED = 'auth.password_changed';
    case AUTH_LOGIN_FAILED = 'auth.login_failed';
    case AUTH_LOGIN_BLOCKED = 'auth.login_blocked';

    // User management
    case USER_CREATED = 'user.created';
    case USER_UPDATED = 'user.updated';
    case USER_ROLE_CHANGED = 'user.role_changed';
    case USER_ACTIVATED = 'user.activated';
    case USER_DEACTIVATED = 'user.deactivated';
    case USER_PASSWORD_RESET = 'user.password_reset';
    case USER_DELETED = 'user.deleted';

    // System
    case SYSTEM_HYPERSWITCH_UNREACHABLE = 'system.hyperswitch_unreachable';

    /**
     * [category, level, label, state of the operation after it (or null), label of that state]
     *
     * @return array{0: Category, 1: Level, 2: string, 3: ?State, 4: ?string}
     */
    protected function definition(): array
    {
        return match ($this) {
            self::PAYMENT_LINK_CREATED => [Category::PAYMENT, Level::SUCCESS, 'Lien de paiement créé', State::PENDING, 'En attente du client'],
            self::PAYMENT_LINK_EMAILED => [Category::PAYMENT, Level::SUCCESS, 'Lien envoyé par e-mail', null, null],
            self::PAYMENT_LINK_EMAIL_FAILED => [Category::PAYMENT, Level::WARNING, 'E-mail du lien non envoyé', State::WARNING, 'E-mail non envoyé'],
            self::PAYMENT_LINK_UNCONFIRMED => [Category::PAYMENT, Level::WARNING, 'Lien non confirmé par Hyperswitch', State::WARNING, 'À vérifier'],
            self::PAYMENT_LINK_REFUSED => [Category::PAYMENT, Level::FAILURE, 'Création du lien refusée', State::FAILURE, 'Refusée à la création'],
            self::PAYMENT_LINK_CONFIRMED => [Category::PAYMENT, Level::INFO, 'Lien confirmé après vérification', State::PENDING, 'En attente du client'],
            self::PAYMENT_NOT_CREATED => [Category::PAYMENT, Level::WARNING, 'Paiement jamais créé chez Hyperswitch', State::WARNING, 'Non créé'],
            self::PAYMENT_LINK_CANCELLED => [Category::PAYMENT, Level::INFO, 'Lien de paiement annulé', State::NEUTRAL, 'Annulé'],
            self::PAYMENT_CLIENT_RETURNED => [Category::PAYMENT, Level::INFO, 'Client revenu de la banque', null, null],
            self::PAYMENT_PAID => [Category::PAYMENT, Level::SUCCESS, 'Paiement reçu', State::SUCCESS, 'Payé'],
            self::PAYMENT_FAILED => [Category::PAYMENT, Level::FAILURE, 'Paiement refusé par la banque', State::FAILURE, 'Refusé par la banque'],
            self::PAYMENT_EXPIRED => [Category::PAYMENT, Level::WARNING, 'Lien de paiement expiré', State::WARNING, 'Expiré'],
            self::PAYMENT_TOTAL_CHANGED => [Category::PAYMENT, Level::INFO, 'Total du contrat modifié', State::SUCCESS, 'Total modifié'],

            self::REFUND_REQUESTED => [Category::REFUND, Level::INFO, 'Remboursement demandé', State::PENDING, 'Remboursement demandé'],
            self::REFUND_BLOCKED => [Category::REFUND, Level::WARNING, 'Remboursement non envoyé', null, null],
            self::REFUND_SUCCEEDED => [Category::REFUND, Level::SUCCESS, 'Remboursement effectué', State::SUCCESS, 'Remboursé'],
            self::REFUND_PENDING => [Category::REFUND, Level::INFO, 'Remboursement en attente de la banque', State::PENDING, 'Remboursement en cours'],
            self::REFUND_UNCONFIRMED => [Category::REFUND, Level::WARNING, 'Remboursement non confirmé par Hyperswitch', State::WARNING, 'Remboursement à vérifier'],
            self::REFUND_FAILED => [Category::REFUND, Level::FAILURE, 'Remboursement échoué', State::FAILURE, 'Remboursement échoué'],
            self::REFUND_FOLLOW_UP_STOPPED => [Category::REFUND, Level::WARNING, 'Suivi automatique du remboursement arrêté', State::WARNING, 'Remboursement à vérifier'],

            self::GOOGLE_ADS_LEAD_CREATED => [Category::GOOGLE_ADS, Level::SUCCESS, 'Lead créé depuis Google Ads', State::SUCCESS, 'Lead créé'],
            self::GOOGLE_ADS_TEST_RECEIVED => [Category::GOOGLE_ADS, Level::INFO, 'Envoi de test Google Ads reçu', State::NEUTRAL, 'Test reçu'],
            self::GOOGLE_ADS_DUPLICATE_IGNORED => [Category::GOOGLE_ADS, Level::INFO, 'Même envoi reçu à nouveau', null, null],
            self::GOOGLE_ADS_REJECTED => [Category::GOOGLE_ADS, Level::FAILURE, 'Lead Google Ads rejeté', State::FAILURE, 'Rejeté'],

            self::AUTH_LOGIN => [Category::AUTH, Level::SUCCESS, 'Connexion', State::SUCCESS, 'Connecté'],
            self::AUTH_LOGOUT => [Category::AUTH, Level::INFO, 'Déconnexion', State::NEUTRAL, 'Déconnecté'],
            self::AUTH_PASSWORD_CHANGED => [Category::AUTH, Level::INFO, 'Mot de passe modifié', null, null],
            self::AUTH_LOGIN_FAILED => [Category::AUTH, Level::WARNING, 'Échec de connexion', State::WARNING, 'Échec de connexion'],
            self::AUTH_LOGIN_BLOCKED => [Category::AUTH, Level::WARNING, 'Connexion bloquée', State::WARNING, 'Connexion bloquée'],

            self::USER_CREATED => [Category::USER, Level::INFO, 'Utilisateur créé', State::SUCCESS, 'Créé'],
            self::USER_UPDATED => [Category::USER, Level::INFO, 'Utilisateur modifié', State::SUCCESS, 'Modifié'],
            self::USER_ROLE_CHANGED => [Category::USER, Level::INFO, 'Rôle modifié', State::SUCCESS, 'Rôle modifié'],
            self::USER_ACTIVATED => [Category::USER, Level::INFO, 'Utilisateur activé', State::SUCCESS, 'Activé'],
            self::USER_DEACTIVATED => [Category::USER, Level::INFO, 'Utilisateur désactivé', State::NEUTRAL, 'Désactivé'],
            self::USER_PASSWORD_RESET => [Category::USER, Level::INFO, 'Mot de passe réinitialisé', State::SUCCESS, 'Mot de passe réinitialisé'],
            self::USER_DELETED => [Category::USER, Level::INFO, 'Utilisateur supprimé', State::NEUTRAL, 'Supprimé'],

            self::SYSTEM_HYPERSWITCH_UNREACHABLE => [Category::SYSTEM, Level::WARNING, 'Hyperswitch ne répond pas', State::WARNING, 'Incident'],
        };
    }

    public function category(): Category
    {
        return $this->definition()[0];
    }

    public function level(): Level
    {
        return $this->definition()[1];
    }

    public function label(): string
    {
        return $this->definition()[2];
    }

    /**
     * State this event leaves its operation in, or null when it doesn't change it.
     */
    public function state(): ?State
    {
        return $this->definition()[3];
    }

    public function stateLabel(): ?string
    {
        return $this->definition()[4];
    }
}
