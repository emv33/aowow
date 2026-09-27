<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * accessed via account settings form submit
 * write status to session and redirect to account settings
 */

class AccountUpdategeneralsettingsResponse extends TextResponse
{
    protected ?string $redirectTo    = '?account#general';
    protected  bool   $requiresLogin = true;

    protected  array  $expectedPOST  = array(
        'modelrace'   => ['filter' => FILTER_VALIDATE_INT, 'options' => ['default' => 0, 'min_range' => 1, 'max_range' => 11]],
        'modelgender' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['default' => 0, 'min_range' => 1, 'max_range' => 2] ]
    );

    private bool $success = false;

    protected function generate() : void
    {
        if (User::isBanned())
            return;

        if ($message = $this->updateGeneral())
            $_SESSION['msg'] = ['general', $this->success, $message];
    }

    private function updateGeneral() : string
    {
        if (!$this->assertPOST('modelrace', 'modelgender'))
            return Lang::main('genericError');

        if ($this->_post['modelrace'] && !ChrRace::tryFrom($this->_post['modelrace']))
            return Lang::main('genericError');

        // js handles this as cookie, so saved as cookie; Q - also save in ::account table?
        if (!DB::Aowow()->qry('REPLACE INTO ::account_cookies (`userId`, `name`, `data`) VALUES (%i, %s, %s)', User::$id, 'default_3dmodel', $this->_post['modelrace']. ',' . $this->_post['modelgender']))
            return Lang::main('genericError');

        if (!setcookie('default_3dmodel', $this->_post['modelrace']. ',' . $this->_post['modelgender'], 0, '/'))
            return Lang::main('intError');

        $this->success = true;
        return Lang::account('updateMessage', 'general');
    }
}

?>
