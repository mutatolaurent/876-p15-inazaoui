<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * @extends AbstractType<User>
 */
class GuestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $passwordConstraints = [
            new NotBlank(message: 'Veuillez saisir un mot de passe.', groups: ['password']),
            new Length([
                'min' => 8,
                'minMessage' => 'Le mot de passe doit faire au moins {{ limit }} caractères.',
                'max' => 4096,
                'groups' => ['password'],
            ]),
            new Regex([
                'pattern' => '/[A-Z]/',
                'message' => 'Le mot de passe doit contenir au moins une lettre majuscule.',
                'groups' => ['password'],
            ]),
            new Regex([
                'pattern' => '/[0-9]/',
                'message' => 'Le mot de passe doit contenir au moins un chiffre.',
                'groups' => ['password'],
            ]),
        ];

        $builder
        ->add('name', TextType::class, [
            'label' => 'Nom',
            'constraints' => [
                new NotNull(message: 'Veuillez saisir un nom.'),
            ],
        ])
        ->add('email', EmailType::class, [
            'label' => 'Email',
            'empty_data' => '',
            'constraints' => [
                new NotBlank(message: 'Veuillez saisir un email.'),
            ],
        ])
        ->add('description', TextareaType::class, [
            'label' => 'Description',
            'constraints' => [
                new NotNull(message: 'Veuillez saisir une description.'),
            ],
        ])
        ->add('password', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'invalid_message' => 'Le mot de passe ne correspond pas.',
            'required' => !$options['is_edit'],
            'help' => $options['is_edit'] ? 'Laissez vide pour conserver le mot de passe actuel.' : null,
            'first_options' => [
                'label' => $options['is_edit'] ? 'Nouveau mot de passe' : 'Mot de passe',
                'attr' => ['placeholder' => $options['is_edit'] ? '••••••••' : ''],
            ],
            'second_options' => [
                'label' => $options['is_edit'] ? 'Confirmation du nouveau mot de passe' : 'Confirmation mot de passe',
            ],
            'constraints' => $passwordConstraints,
        ]);
    }

    /**
     * Configure les options par défaut pour le formulaire.
     *
     * @param OptionsResolver $resolver Le résolveur d'options
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        // Définit les options par défaut pour le formulaire, y compris la classe de données associée et les groupes de validation
        $resolver->setDefaults([
            'data_class' => User::class,        // La classe de données associée au formulaire est User
            'is_edit' => false,             // Indique si le formulaire est utilisé pour l'édition (true) ou l'ajout (false) d'un invité
            'validation_groups' => static function (FormInterface $form): array {
                $password = $form->get('password')->getData();
                $isEdit = $form->getConfig()->getOption('is_edit');

                if (!$isEdit || (null !== $password && '' !== $password)) {
                    return ['Default', 'password'];
                }

                return ['Default'];
            },
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
