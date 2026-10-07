<?php

use Illuminate\Support\Facades\Broadcast;

/*
| Canal privado por usuario para notificaciones en tiempo real.
| Solo el propio usuario (activo) puede suscribirse.
*/
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id && (bool) $user->activo;
});
