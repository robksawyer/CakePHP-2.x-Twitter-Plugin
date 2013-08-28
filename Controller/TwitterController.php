<?php

class TwitterController extends TwitterAppController {
/**
 * Name
 *
 * @var string
 */
	public $name = 'Twitter';

/**
 * Uses
 * 
 * @var string
 */
	public $uses = array('User'); //Errors will be thrown if this is a string
	
/**
 * Consumer Secret from Twitter App
 * Set from within the Config/twitter.php file.
 * 
 * @var string
 */
	public $consumerKey = '';
	
/**
 * Consumer Secret from Twitter App
 * Set from within the Config/twitter.php file.
 * 
 * @var string
 */
	public $consumerSecret = '';

/**
 * Plugin that contains the model that saves authorization values. 
 * 
 * @var string
 */
	public $savePlugin = '';
	
/**
 * Model to save authorization values to. 
 * Table must have user_id, type, and value fields
 * 
 * @var string
 */
	public $saveModel = 'User';
	
/**
 * components
 * 
 * @var string
 */
	public $components = array('Twitter.Twitter');

/**
 * helpers
 * 
 * @var string
 */
	public $helpers = array('Session');
	
/**
 * Controller construct (loads config file)
 * 
 * @return null
 */
	public function __construct($request = null, $response = null) {
		parent::__construct($request, $response);	
		Configure::load('Twitter.twitter', 'default', false);	
		$this->consumerKey = Configure::read('Twitter.consumerKey');
		$this->consumerSecret = Configure::read('Twitter.consumerSecret');
	}
	
	
/**
 * Save the user data to the application.
 * Configure the saveModel and savePlugin at the top of this controller.
 * 
 * @return bool
 * @todo 	Make this model name variable so that anyone using this plugin can easily change the table it saves data to.
 */
	protected function _connectUser($profileData, $verifier, $token) {
		if (!empty($this->saveModel)) {
			//debug($profileData);

			if(isset($this->current_user['User']['id'])){
				//Update the user's account with the profileData 
				if(!empty($profileData['profile'])){
					$data = array(
						'id' => intval($this->current_user['User']['id']),
						'twitter_id' => $profileData['profile']['id'],
						'twitter_oauth_token' => $profileData['oauth_token'],
						'twitter_oauth_token_secret' => $profileData['oauth_token_secret'],
						'oauth_uid' => $token,
						'oauth_provider' => $verifier
					);
					//Fill in some data that may be empty
					if(empty($this->current_user['User']['name'])) $data['name'] = $profileData['profile']['name'];
					if(empty($this->current_user['User']['url'])) $data['name'] = $profileData['profile']['url'];
					if(empty($this->current_user['User']['about'])) $data['about'] = $profileData['profile']['description'];
					if(empty($this->current_user['User']['profile_image_url'])) $data['profile_image_url'] = $profileData['profile']['profile_image_url'];
					
					//Check the data and save
					if(!empty($data)){
						if($this->User->save($data,array('validate'=>false))){
							//Update the user session
							$user = $this->User->find('first', array(
								'conditions' => array(
									'User.id' => $this->current_user['User']['id']
								),
								'contain' => array(
									'UserFollower', 'Attachment', 'StateRegion','Country',
								),
								'recursive' => -1
							));
							if(!empty($user)){
								//Delete cached user
								Cache::delete('current_user_' . $this->current_user['User']['id'], 'hour');
								$this->Session->write('Auth.User', $user['User']); //Update the session
								$this->current_user = $user;
							}
							return true;
						}else{
							return false;
						}
					}
				}
			}
		} else {
			return true;
		}
	}

/**
 * connect method
 */
	public function connect() {
		if($this->Session->check('Twitter.User')){
			CakeSession::delete('Twitter.User');
		}
		if (!empty($this->consumerKey) && !empty($this->consumerSecret)) {
			$this->Twitter->setupApp($this->consumerKey, $this->consumerSecret);
			$this->Twitter->connectApp(Router::url(array('action' => 'authorization'), true));
		} else {
			echo 'App key and secret key are not set';
			break;
		}
	}
	
/**
 * authorization method
 */
	public function authorization() { 
		if (!empty($this->request->query['oauth_token']) && !empty($this->request->query['oauth_verifier'])) {
			$this->Twitter->authorizeTwitterUser($this->request->query['oauth_token'], $this->request->query['oauth_verifier']);
			# connect the user to the application
			/*try {
				$user = $this->Twitter->getTwitterUser(true);
				$this->_connectUser($user, $this->request->query['oauth_verifier'], $this->request->query['oauth_token']);
				$this->Session->setFlash('Test status message sent.');
				$this->redirect(array('plugin'=>false,'controller'=>'users','action' => 'edit_social_connections'));
			} catch (Exception $e) {
				$this->Session->setFlash($e->getMessage());
				$this->redirect(array('plugin'=>false,'controller'=>'users','action' => 'edit_social_connections'));
			}*/
			$user = $this->Twitter->getTwitterUser(true);
			$this->_connectUser($user, $this->request->query['oauth_verifier'], $this->request->query['oauth_token']);
			$this->redirect(array('plugin'=>false,'controller'=>'users','action' => 'edit_social_connections'));
		} else {
			$this->Session->setFlash('Invalid authorization request.');
			$this->redirect(array('plugin'=>false,'controller'=>'users','action' => 'edit_social_connections'));
		}
	}

/**
 * dashboard method
 * 
 */
	/*public function dashboard() {
		if (!empty($this->request->data['Twitter']['status'])) {
			if ($this->Twitter->updateStatus($this->request->data['Twitter']['status'])) {
				$this->Session->setFlash('Status updated.');
			} else {	
				$this->Session->setFlash('Status update failed');
			}			
		}
		
		$status = true;
		$reload = false;
		$credentialCheck = false;
		$user = false;
		
		if (!empty($this->saveModel)) {
			$credentialCheck = $this->Twitter->accountVerifyCredentials();
			if (!empty($credentialCheck['error'])) {
				$status = false;
				
				App::uses($this->saveModel, $this->savePlugin . '.Model');
				$UserConnect = new UserConnect;
				
				$user = $UserConnect->find('first', array(
					'conditions' => array(
						'UserConnect.type' => 'twitter',
						'UserConnect.user_id' => CakeSession::read('Auth.User.id'),
						),
					));
				$twitterUser = CakeSession::read('Twitter.User');
						
				if (!empty($user) && empty($twitterUser)) {
					$twitterUser = unserialize($user['UserConnect']['value']);
					CakeSession::write('Twitter.User.oauth_token', $twitterUser['oauth_token']);
					CakeSession::write('Twitter.User.oauth_token_secret', $twitterUser['oauth_token_secret']);
					$reload = true;
				} else if (!empty($user)) {
					$reload = false;
				}
			} else {
				$status = true;
			}
		}
		$this->set(compact('status', 'reload', 'credentialCheck', 'user')); 
	}*/

}	
