<?php

declare(strict_types=1);

final class ChatbotService
{
    public static function config(): array
    {
        return [
            'enabled' => Env::bool('CHATBOT_ENABLED', true),
            'brandName' => Env::get('CHATBOT_BRAND_NAME', 'Pure Essence Vita'),
            'agentName' => Env::get('CHATBOT_AGENT_NAME', 'Essence'),
            'welcome' => Env::get(
                'CHATBOT_WELCOME',
                'Bonjour ! Je suis Essence, votre assistante Pure Essence Vita. Comment puis-je vous aider ?'
            ),
            'fallback' => Env::get(
                'CHATBOT_FALLBACK',
                'Je n\'ai pas bien compris. Choisissez une option ci-dessous ou contactez-nous sur WhatsApp.'
            ),
            'whatsapp' => Env::get('WHATSAPP_NUMBER', '221771234567'),
            'country' => Env::get('PAYMENT_COUNTRY', 'SN'),
            'currency' => Env::get('PAYMENT_CURRENCY', 'XOF'),
            'quickReplies' => [
                ['id' => 'products', 'label' => 'Voir les produits'],
                ['id' => 'payment_wave', 'label' => 'Payer avec Wave'],
                ['id' => 'payment_orange', 'label' => 'Payer avec Orange Money'],
                ['id' => 'track', 'label' => 'Suivre ma commande'],
                ['id' => 'delivery', 'label' => 'Livraison & délais'],
                ['id' => 'whatsapp', 'label' => 'Parler sur WhatsApp'],
            ],
        ];
    }

    public static function reply(string $message, ?string $intent = null): array
    {
        $text = mb_strtolower(trim($message));
        $intent = $intent ?: self::detectIntent($text);
        $cfg = self::config();

        $responses = [
            'greeting' => [
                'text' => $cfg['welcome'],
                'quickReplies' => ['products', 'payment_wave', 'track', 'whatsapp'],
            ],
            'products' => [
                'text' => "Découvrez notre collection de compléments premium : beauté, énergie, homme, femme et enfants.\n\nConsultez la boutique : index.html#produits",
                'quickReplies' => ['payment_wave', 'delivery', 'whatsapp'],
            ],
            'payment_wave' => [
                'text' => "Paiement Wave (Sénégal) :\n1. Ajoutez vos produits au panier\n2. Au checkout, choisissez Wave\n3. Entrez votre numéro Wave (77… / 78…)\n4. Validez sur l'application Wave\n\nLivraison à Dakar et partout au Sénégal.",
                'quickReplies' => ['payment_orange', 'track', 'products'],
            ],
            'payment_orange' => [
                'text' => "Paiement Orange Money (Sénégal) :\n1. Ajoutez vos produits au panier\n2. Au checkout, choisissez Orange Money\n3. Entrez votre numéro OM\n4. Confirmez via #144# ou l'app Orange Money\n\nPaiement sécurisé en FCFA.",
                'quickReplies' => ['payment_wave', 'track', 'whatsapp'],
            ],
            'payment' => [
                'text' => "Nous acceptons au Sénégal :\n• Wave — paiement mobile instantané\n• Orange Money — paiement mobile sécurisé\n• Paiement à la livraison — espèces au livreur\n\nQuel moyen préférez-vous ?",
                'quickReplies' => ['payment_wave', 'payment_orange', 'delivery'],
            ],
            'track' => [
                'text' => "Pour suivre votre commande :\n1. Allez sur Suivi commande\n2. Entrez votre N° de commande ou numéro de suivi\n3. Statut en temps réel\n\n→ suivi.html",
                'quickReplies' => ['products', 'whatsapp', 'delivery'],
            ],
            'delivery' => [
                'text' => "Livraison Pure Essence Vita :\n• Frais : 2 000 FCFA\n• Gratuite dès 50 000 FCFA\n• Délai : 2 à 4 jours au Sénégal\n• Livraison soignée à Dakar et régions",
                'quickReplies' => ['products', 'payment_wave', 'whatsapp'],
            ],
            'contact' => [
                'text' => "Notre équipe est disponible pour vous accompagner.\n• WhatsApp : contact direct\n• Email : contact@pureessencevita.com\n• Formulaire : section Contact du site",
                'quickReplies' => ['whatsapp', 'products', 'track'],
            ],
            'whatsapp' => [
                'text' => "Je vous redirige vers WhatsApp pour un échange direct avec notre équipe. Un conseiller vous répondra rapidement.",
                'action' => 'whatsapp',
                'quickReplies' => ['products', 'track'],
            ],
            'thanks' => [
                'text' => "Avec plaisir ! N'hésitez pas si vous avez d'autres questions. Bonne journée et prenez soin de vous.",
                'quickReplies' => ['products', 'whatsapp'],
            ],
        ];

        if (!isset($responses[$intent])) {
            return [
                'text' => $cfg['fallback'],
                'intent' => 'fallback',
                'quickReplies' => ['products', 'payment_wave', 'payment_orange', 'track', 'whatsapp'],
            ];
        }

        $reply = $responses[$intent];
        $reply['intent'] = $intent;
        return $reply;
    }

    private static function detectIntent(string $text): string
    {
        if ($text === '' || preg_match('/^(bonjour|salut|bonsoir|hello|hi|coucou|hey)\b/u', $text)) {
            return 'greeting';
        }
        if (preg_match('/wave/u', $text)) {
            return 'payment_wave';
        }
        if (preg_match('/orange|om\b|orange money/u', $text)) {
            return 'payment_orange';
        }
        if (preg_match('/paiement|payer|payement|mobile money/u', $text)) {
            return 'payment';
        }
        if (preg_match('/suiv(re|i)|commande|tracking|colis/u', $text)) {
            return 'track';
        }
        if (preg_match('/livraison|livrer|délai|delai|frais|expédition|expedition/u', $text)) {
            return 'delivery';
        }
        if (preg_match('/produit|catalogue|collection|acheter|boutique/u', $text)) {
            return 'products';
        }
        if (preg_match('/whatsapp|watsapp|contacter|contact|appeler|téléphone|telephone/u', $text)) {
            return 'whatsapp';
        }
        if (preg_match('/merci|thank/u', $text)) {
            return 'thanks';
        }

        return 'fallback';
    }
}
