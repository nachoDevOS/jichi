<?php

namespace App\Http\Requests\Panel;

/**
 * Reglas para reponer un carnet: las mismas que revocar, porque eso es lo primero que hace.
 */
class ReponerCarnetRequest extends RevocarCarnetRequest {}
