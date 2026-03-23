<?php
/**
 * Legacy Master Layout
 * Redirects to the Superpowers Reactive Shell
 */
echo $this->render('layouts/shell', array_merge($data ?? [], ['slot' => $this->yield('content')]), null);
