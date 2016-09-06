<?php
namespace FIT\NetopeerBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('username', 'email', array(
				'label' => 'Email'
			))
			->add('password', 'repeated', array(
					'type' => 'password',
					'first_options'  => array('label' => 'Password'),
					'second_options' => array('label' => 'Repeat'),
				)
			);
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			'data_class' => 'FIT\NetopeerBundle\Entity\User',
		));
	}

	public function getName()
	{
		return 'user';
	}
}