<?php
/*
* @package      SMF Login
* @copyright    Copyright (C) 2016 Emir Sakic, http://www.sakic.net. All rights reserved.
* @license      GNU/GPL, see LICENSE.TXT
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This header must not be removed. Additional contributions/changes
* may be added to this header as long as no information is deleted.
*/

defined('JPATH_BASE') or die;

/**
 * An example custom profile plugin.
 *
 * @package     Joomla.Plugin
 * @subpackage  User.profile
 * @version     1.6
 */
class PlgAuthenticationSMF extends JPlugin
{

    /**
     * This method should handle any authentication and report back to the subject
     *
     * @param   array   $credentials  Array holding the user credentials
     * @param   array   $options      Array of extra options
     * @param   object  &$response    Authentication response object
     *
     * @return  boolean
     *
     * @since   1.5
     */
    public function onUserAuthenticate($credentials, $options, &$response)
    {
        // For JLog
        $response->type = 'SMF';

        if (empty($credentials['password']))
        {
            $response->status = JAuthentication::STATUS_FAILURE;
            $response->error_message = JText::_('JGLOBAL_AUTH_PASS_BLANK');

            return false;
        }

        // Get a database object
        $db    = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select('id, password')
            ->from('#__users')
            ->where('username=' . $db->quote($credentials['username']));

        $db->setQuery($query);
        $result = $db->loadObject();

        if ($result) {
            $hash = sha1(strtolower($credentials['username']) . $credentials['password']);
            if ($hash == $result->password) {
                // Bring this in line with the rest of the system
                $user               = JUser::getInstance($result->id);
                $response->email    = $user->email;
                $response->fullname = $user->name;

                if (JFactory::getApplication()->isAdmin())
                {
                    $response->language = $user->getParam('admin_language');
                }
                else
                {
                    $response->language = $user->getParam('language');
                }

                $response->status        = JAuthentication::STATUS_SUCCESS;
                $response->error_message = '';

                // convert to Joomla password hash
                $password = JUserHelper::hashPassword($credentials['password']);
                $obj = new JObject;
                $obj->id = $result->id;
                $obj->password = $password;
                $db->updateObject('#__users', $obj, 'id');

            } else {
                // Invalid password
                $response->status        = JAuthentication::STATUS_FAILURE;
                $response->error_message = JText::_('JGLOBAL_AUTH_INVALID_PASS');
            }
        } else {
            // Invalid user
            $response->status        = JAuthentication::STATUS_FAILURE;
            $response->error_message = JText::_('JGLOBAL_AUTH_NO_USER');
        }

    }

}