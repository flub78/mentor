<?php

namespace App\Banques\Questions;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * Comportement propre à un type de question (PRD Banques §6) : forme de la
 * réponse attendue (`payload`), saisie et correction de la réponse.
 * Ajouter un type = ajouter une classe et une vue de saisie.
 */
interface QuestionTypeHandler
{
    /**
     * Champs du formulaire de la réponse attendue, avec des chemins d'état
     * `payload.*`.
     *
     * @return list<Component|Field>
     */
    public function formSchema(): array;

    /**
     * Règles de validation du `payload`, clés relatives au `payload`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Messages de validation en français, qui nomment le champ à corriger.
     *
     * @return array<string, string>
     */
    public function messages(): array;

    /**
     * Forme canonique du `payload` enregistré (types, listes réindexées).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array;

    /**
     * Corrige une réponse.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidResponse si la réponse ne peut pas être corrigée
     */
    public function evaluate(array $payload, mixed $response): Evaluation;

    /**
     * Réponse attendue, en texte, affichée après le verdict.
     *
     * @param  array<string, mixed>  $payload
     */
    public function expectedAnswer(array $payload): string;

    /**
     * Vue Blade de saisie de la réponse pendant une séance Leitner (plan Leitner,
     * étape 4).
     */
    public function answerView(): string;
}
