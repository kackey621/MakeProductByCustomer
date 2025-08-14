<?php

namespace Plugin\MPBC43\Form\Type\Admin;

use Plugin\MPBC43\Entity\Config;
use Eccube\Entity\Layout;
use Eccube\Repository\LayoutRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class ConfigType extends AbstractType
{
    /**
     * @var LayoutRepository
     */
    private $layoutRepository;

    /**
     * ConfigType constructor.
     * @param LayoutRepository $layoutRepository
     */
    public function __construct(LayoutRepository $layoutRepository)
    {
        $this->layoutRepository = $layoutRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('product_name_display_type', ChoiceType::class, [
            'label' => '商品名の表示方法',
            'choices' => [
                'お客様が入力' => 'customer_input',
                '店側が設定した商品名を表示' => 'predefined_name',
            ],
            'expanded' => true,
            'multiple' => false,
            'required' => true,
        ]);

        $builder->add('predefined_product_name', TextType::class, [
            'label' => '店側設定商品名',
            'required' => false,
            'constraints' => [
                new Length(['max' => 255]),
            ],
            'attr' => [
                'placeholder' => '店側が設定した商品名を表示する場合の商品名を入力してください',
            ],
        ]);

        // レイアウト一覧を取得
        $layouts = $this->layoutRepository->findAll();
        $layoutChoices = [];
        foreach ($layouts as $layout) {
            $layoutChoices[$layout->getName()] = $layout->getId();
        }

        $builder->add('page_layout', ChoiceType::class, [
            'label' => 'ページレイアウト',
            'choices' => $layoutChoices,
            'expanded' => false,
            'multiple' => false,
            'required' => true,
            'placeholder' => 'レイアウトを選択してください',
        ]);

        $builder->add('page_title', TextType::class, [
            'label' => 'ページタイトル',
            'required' => false,
            'constraints' => [
                new Length(['max' => 255]),
            ],
            'attr' => [
                'placeholder' => 'カスタムページタイトルを入力してください（空白の場合はデフォルト）',
            ],
        ]);

        $builder->add('page_description', TextareaType::class, [
            'label' => 'ページ説明文',
            'required' => false,
            'attr' => [
                'rows' => 3,
                'placeholder' => 'ページの説明文を入力してください',
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }
}
