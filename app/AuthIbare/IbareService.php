<?php

namespace App\AuthIbare;

use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * Login con Ibare: Authorization Code + PKCE. Ibare autentica y revisa el
 * contrato; Jichi solo resuelve QUÉ usuario suyo es. Ver docs/modulos/IBARE.md.
 */
class IbareService
{
    private const SESION = 'ibare';

    private const CACHE_JWKS = 'ibare.jwks';

    public function activo(): bool
    {
        return (bool) config('jichi.ibare.activo');
    }

    /** Arma la URL de autorización y guarda en sesión el `state` y el verificador PKCE. */
    public function urlAutorizacion(Request $request): string
    {
        $state = Str::random(40);
        $verificador = Str::random(64);

        $request->session()->put(self::SESION, ['state' => $state, 'verificador' => $verificador]);

        return $this->url('/oauth/authorize').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('jichi.ibare.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'code_challenge' => $this->base64Url(hash('sha256', $verificador, true)),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Procesa la vuelta de Ibare y devuelve el usuario de Jichi que corresponde.
     *
     * @throws IbareException
     */
    public function usuarioDesdeCallback(Request $request): User
    {
        // pull y no get: el `state` sirve una sola vez.
        $guardado = $request->session()->pull(self::SESION);

        if ($request->filled('error')) {
            throw IbareException::rechazado();
        }

        $state = $request->query('state');
        $codigo = $request->query('code');

        if (! is_array($guardado) || ! is_string($state) || ! hash_equals($guardado['state'], $state)) {
            throw IbareException::solicitudVencida();
        }

        if (! is_string($codigo) || $codigo === '') {
            throw IbareException::rechazado();
        }

        $mamoreId = $this->mamoreIdDelToken($this->canjearCodigo($codigo, $guardado['verificador']));

        $usuario = User::where('mamore_id', $mamoreId)->where('activo', true)->first();

        if (! $usuario) {
            throw IbareException::sinCuenta($mamoreId);
        }

        return $usuario;
    }

    private function canjearCodigo(string $codigo, string $verificador): string
    {
        try {
            $respuesta = Http::asForm()->acceptJson()
                ->timeout(config('jichi.ibare.timeout'))
                ->post($this->url('/oauth/token'), [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('jichi.ibare.client_id'),
                    'client_secret' => config('jichi.ibare.client_secret'),
                    'redirect_uri' => $this->redirectUri(),
                    'code' => $codigo,
                    'code_verifier' => $verificador,
                ]);
        } catch (ConnectionException) {
            throw IbareException::noResponde();
        }

        $token = $respuesta->json('access_token');

        if (! $respuesta->successful() || ! is_string($token)) {
            Log::warning('Ibare rechazó el canje del código', ['status' => $respuesta->status(), 'cuerpo' => $respuesta->body()]);

            throw IbareException::tokenInvalido();
        }

        return $token;
    }

    /** Valida firma, vencimiento y destinatario del JWT; devuelve su `sub`. */
    private function mamoreIdDelToken(string $token): string
    {
        // Si falla con el JWKS en caché, puede ser una clave rotada: se pide de nuevo una vez.
        $datos = $this->decodificar($token) ?? $this->decodificar($token, refrescar: true);

        if ($datos === null) {
            throw IbareException::tokenInvalido();
        }

        $destinatarios = (array) ($datos->aud ?? []);

        if (! in_array(config('jichi.ibare.client_id'), $destinatarios, true)
            || ($datos->tipo_sujeto ?? null) !== 'funcionario'
            || empty($datos->sub)) {
            throw IbareException::tokenInvalido();
        }

        return (string) $datos->sub;
    }

    private function decodificar(string $token, bool $refrescar = false): ?stdClass
    {
        if ($refrescar) {
            Cache::forget(self::CACHE_JWKS);
        }

        $claves = JWK::parseKeySet($this->jwks(), 'RS256');

        try {
            return JWT::decode($token, $claves);
        } catch (Throwable $e) {
            Log::warning('Token de Ibare inválido: '.$e->getMessage());

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function jwks(): array
    {
        return Cache::remember(self::CACHE_JWKS, now()->addHour(), function () {
            try {
                $respuesta = Http::acceptJson()->timeout(config('jichi.ibare.timeout'))->get($this->url('/.well-known/jwks.json'));
            } catch (ConnectionException) {
                throw IbareException::noResponde();
            }

            if (! $respuesta->successful() || ! is_array($respuesta->json('keys'))) {
                throw IbareException::noResponde();
            }

            return $respuesta->json();
        });
    }

    // Con APP_URL y no route() absoluta: tiene que coincidir con la registrada en Ibare, venga de donde venga la petición.
    private function redirectUri(): string
    {
        return rtrim((string) config('app.url'), '/').route('ibare.callback', [], false);
    }

    private function url(string $ruta): string
    {
        return config('jichi.ibare.url').$ruta;
    }

    private function base64Url(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }
}
