<?php
namespace FIT\NetopeerBundle\Controller;

use FIT\NetopeerBundle\Controller\BaseController;
use FIT\NetopeerBundle\Entity\User;
use FIT\NetopeerBundle\Form\UserType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class RegistrationController extends BaseController {
	/**
	 * Register page action.
	 *
	 * @Route("/register", name="register")
	 * @Template()
	 */
	public function registerAction()
	{
		// 1) build the form
		$user = new User();
		$form = $this->createForm(new UserType(), $user);

		// 2) handle the submit (will only happen on POST)
		$form->handleRequest($this->getRequest());
		if ($form->isSubmitted() && $form->isValid()) {

			// 3) Encode the password (you could also do this via Doctrine listener)
			$password = $this->get('security.encoder_factory')->getEncoder($user)
			                 ->encodePassword($user->getPassword(), $user->getSalt());
			$user->setPassword($password);
			$user->setRoles("ROLE_ADMIN");

			// 4) save the User!
			$em = $this->getDoctrine()->getManager();
			$repository = $em->getRepository('FITNetopeerBundle:User');

			$tmpUser = $repository->findOneBy(
				array('username' => $user->getUsername())
			);
			if ($tmpUser) {
				$this->getRequest()->getSession()->getFlashBag()->add('error', 'User with username "'.$user->getUsername().'" already exists.');
			} else {
				$em->persist($user);
				$em->flush();
				$this->getRequest()->getSession()->getFlashBag()->add('success', 'User with username "'.$user->getUsername().'" succesfully registered.');
				return $this->redirect($this->generateUrl('_login'));
			}


		}
		$error = $form->getErrors();

		return ['form' => $form->createView(), 'error' => $error];
	}
}