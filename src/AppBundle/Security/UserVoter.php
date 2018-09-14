<?php
// src/AppBundle/Security/UserVoter.php
namespace AppBundle\Security;

use AppBundle\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    const ACCESS_TOOLS = 'access_tools';
    const CREATE = 'create';
    const VIEW = 'view';
    const EDIT = 'edit';
    const CLOSE = 'close';
    const FREEZE = 'freeze';
    const FREEZE_CHANGE = 'freeze_change';
    const ROLE_REMOVE = 'role_remove';
    const ROLE_ADD = 'role_add';
    const ANNOTATE = 'annotate';

    private $decisionManager;
    private $container;

    public function __construct(ContainerInterface $container,AccessDecisionManagerInterface $decisionManager)
    {
        $this->container = $container;
        $this->decisionManager = $decisionManager;
    }

    protected function supports($attribute, $subject)
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, array(self::VIEW, self::EDIT, self::CLOSE,self::ROLE_REMOVE,self::ROLE_ADD, self::FREEZE,self::FREEZE_CHANGE, self::CREATE,self::ANNOTATE, self::ACCESS_TOOLS))) {
            return false;
        }

        // only vote on Post objects inside this voter
        if (!$subject instanceof User) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // the user must be logged in; if not, deny access
            return false;
        }

        // ROLE_SUPER_ADMIN can do anything! The power!
        if ($this->decisionManager->decide($token, array('ROLE_SUPER_ADMIN'))) {
            return true;
        }
        // on user ROLE_ADMIN can do anything! The power!
        if ($this->decisionManager->decide($token, array('ROLE_ADMIN'))) {
            return true;
        }
        // on user ROLE_USER_MANAGER can do anything! The power!
        if ($this->decisionManager->decide($token, array('ROLE_USER_MANAGER'))) {
            return true;
        }

        // you know $subject is a Post object, thanks to supports
        switch ($attribute) {
            case self::ACCESS_TOOLS:
            case self::CREATE:
                return $this->isLocationOk();
            case self::VIEW:
            case self::ANNOTATE:
                return $this->canView($subject, $user);
            case self::FREEZE_CHANGE:
                if ($subject === $user){
                    return true;
                }
            case self::FREEZE:
            case self::CLOSE:
            case self::ROLE_ADD:
            case self::ROLE_REMOVE:
            case self::EDIT:
                return $this->canEdit($subject, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canView(User $subject, User $user)
    {
        // if they can edit, they can view
        if ($this->canEdit($subject, $user)) {
            return true;
        }
        if ($user->getMainBeneficiary()->canViewUserData()){ //todo check also other Beneficiary ? < todo : use new ROLE_USER_MANAGER
            return true;
        }
        return false;
    }

    private function canEdit(User $subject, User $user)
    {
        $session = new Session();

        $token = $this->container->get('request_stack')->getCurrentRequest()->get('token');

        if ($this->isLocationOk()){
            if ($user->getMainBeneficiary()->canEditUserData()){ //todo check also other Beneficiary ? < todo : use new ROLE_USER_MANAGER
                return true;
            }
            if ($subject->getId()){
                if ($token == $subject->getTmpToken($session->get('token_key').$user->getUsername())){
                    return true;
                }
            }
            return false;
        }
        return false;

    }

    private function isLocationOk(){
        $ip = $this->container->get('request_stack')->getCurrentRequest()->getClientIp();
        $ips = $this->container->getParameter('place_local_ip_address');
        $ips = explode(',',$ips);
        return (isset($ip) and in_array($ip,$ips));
    }
}