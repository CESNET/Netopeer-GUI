<?php
/**
 * Handles pages for login and logout.
 *
 * @author David Alexa
 */
namespace FIT\NetopeerBundle\Controller;

use FIT\NetopeerBundle\Controller\BaseController;
use Symfony\Component\Security\Core\SecurityContext;
use FIT\NetopeerBundle\Entity\User;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * Controller for security pages.
 */
class SecurityController extends BaseController
{

    /**
     * Login page action.
     *
     * @Route("/login/", name="_login")
     * @Template()
     */
    public function loginAction()
    {
        $request = $this->getRequest();
        $session = $request->getSession();

        // get the login error if there is one
        if ($request->attributes->has(SecurityContext::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(SecurityContext::AUTHENTICATION_ERROR);
        } else {
            $error = $session->get(SecurityContext::AUTHENTICATION_ERROR);
            $session->remove(SecurityContext::AUTHENTICATION_ERROR);
        }

        // last username entered by the user
        $this->assign('last_username', $session->get(SecurityContext::LAST_USERNAME));
        $this->assign('error', $error);
        
        return $this->getTwigArr($this);
    }

    /**
     * Logout page action.
     *
     * @Route("/logout/", name="_logout")
     * @Template()
     */
    public function logoutAction()
    {
        // The security layer will intercept this request
    }

		/**
     * Create new user.
		 * @TODO: remove as soon as possible, security danger
     *
     * @Route("/create-user-manually/", name="createUser")
     */
    public function createUserAction()
    {
      $user = new User();
	    $user->setRoles("ROLE_ADMIN");
	    $user->setUsername("tcejka");

	    $encoder = $this->get('security.encoder_factory')->getEncoder($user);
	    $password = $encoder->encodePassword('pass', $user->getSalt());
	    $user->setPassword($password);

	    $em = $this->getDoctrine()->getEntityManager();
	    $em->persist($user);
	    $em->flush();

    }
}
