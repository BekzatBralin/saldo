<?php
// POST /api/auth/logout

clearAuthCookie();
jsonResponse(['ok' => true]);
