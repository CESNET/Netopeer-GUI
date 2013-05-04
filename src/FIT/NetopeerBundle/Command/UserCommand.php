<?php
namespace FIT\NetopeerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use FIT\NetopeerBundle\Entity\User;

/**
 * Handles creating, removing or editing users in DB
 *
 * Class UserCommand
 * @package FIT\NetopeerBundle\Command
 */
class UserCommand extends ContainerAwareCommand
{
	protected function configure() {
		$this->setName("app:user")
			->setHelp("Handles creating, removing or editing users in DB")
			->setDescription("Handles creating, removing or editing users in DB")
			->addOption(
				'action',
				null,
				InputOption::VALUE_OPTIONAL,
				'Set action add|edit|rm',
				'add'
			)
			->addOption(
					'user',
					null,
					InputOption::VALUE_REQUIRED,
					'Set user username'
			)
			->addOption(
				'pass',
				null,
				InputOption::VALUE_REQUIRED,
				'Set user password'
			)
			->addOption(
				'new-username',
				null,
				InputOption::VALUE_REQUIRED,
				'Set user new username'
			);
	}

	/**
	 * Executes adding, removing or editing user in DB
	 *
	 * @param InputInterface $intput
	 * @param OutputInterface $output
	 * @return int|null|void
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$command = $input->getOption("action");

		if (!$command) {
			$command = "add";
		}

		$username = $input->getOption('user');
		$password = $input->getOption('pass');
		$newusername = $input->getOption('new-username');

		if (!$username) {
			$output->writeln('Set --name!');
			return;
		}

		$em = $this->getContainer()->get('doctrine')->getEntityManager();

		if ($command == "add") {
			if ($password) {
				$user = new User();
				$user->setRoles("ROLE_ADMIN");
				$user->setUsername($username);

				$encoder = $this->getContainer()->get('security.encoder_factory')->getEncoder($user);
				$pass = $encoder->encodePassword($password, $user->getSalt());
				$user->setPassword($pass);

				try {
					$em->persist($user);
					$em->flush();
				} catch (\PDOException $e) {
					$output->writeln('User with username "'.$username.'" already exists.');
				}
			} else {
				$output->writeln('Please, set user password: --pass=password');
			}
		} elseif ($command == "edit") {
			$user = $em->getRepository('FITNetopeerBundle:User')->findOneBy(array(
				"username" => $username,
			));

			if (!$user) {
				$output->writeln('Selected user does not exists!');
				return;
			}

			if ($newusername) {
				$user->setUsername($newusername);
			}
			if ($password) {
				$encoder = $this->getContainer()->get('security.encoder_factory')->getEncoder($user);
				$pass = $encoder->encodePassword($password, $user->getSalt());
				$user->setPassword($pass);
			}
			try {
				$em->persist($user);
				$em->flush();
			} catch (\PDOException $e) {
				$output->writeln('Could not edit user  with username "'.$username.'".');
			}
		} elseif ($command == "rm") {
			/** @var $dialog DialogHelper */
			$dialog = $this->getHelperSet()->get('dialog');

			while ( true ) {
				$command = $dialog->ask($output, 'Do you realy want to delete user "'.$username.'"? [y/n]: ');
				try {
					if ( $command ) {
						if ($command == "y") {
							$user = $em->getRepository('FITNetopeerBundle:User')->findOneBy(array(
								"username" => $username,
							));

							if (!$user) {
								$output->writeln('Selected user does not exists!');
								return;
							}

							try {
								$em->remove($user);
								$em->flush();
							} catch (\PDOException $e) {
								$output->writeln('Could not remove user  with username "'.$username.'".');
							}
						}
						return;
					}
				} catch (\Exception $e) {
					$output->writeln('<error>'.$e.'</error>');
				}
			}
			exit;
		}
	}
}