<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Preserva o vínculo atual de cada motorista com a sua empresa (e o valor
        // por entrega já praticado) como um vínculo já aceito, antes de remover
        // as colunas company_id e delivery_fee — a taxa passa a ser negociada
        // por vínculo (driver_company), já que um motorista pode atender várias
        // empresas com valores diferentes.
        DB::table('drivers')->whereNotNull('company_id')->orderBy('id')->each(function ($driver): void {
            DB::table('driver_company')->insertOrIgnore([
                'driver_id' => $driver->id,
                'company_id' => $driver->company_id,
                'status' => 'accepted',
                'delivery_fee' => $driver->delivery_fee,
                'responded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // No modelo antigo, cada empresa cadastrava seu próprio registro de
        // motorista, então é esperado que a mesma pessoa (mesmo telefone) exista
        // como várias linhas duplicadas em drivers — uma por empresa. Antes de
        // tornar phone único, funde essas duplicatas num único motorista
        // (mantém a linha de menor id como "canônica"), migrando os vínculos e
        // entregas de cada duplicata pra ela e removendo o resto. Como
        // consequência, se as duplicatas tinham nome/veículo/cpf diferentes,
        // só o valor da linha canônica sobrevive.
        $duplicatePhones = DB::table('drivers')
            ->select('phone')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        foreach ($duplicatePhones as $phone) {
            $driverIds = DB::table('drivers')->where('phone', $phone)->orderBy('id')->pluck('id');
            $canonicalId = $driverIds->shift();

            foreach ($driverIds as $duplicateId) {
                foreach (DB::table('driver_company')->where('driver_id', $duplicateId)->get() as $link) {
                    $canonicalAlreadyLinked = DB::table('driver_company')
                        ->where('driver_id', $canonicalId)
                        ->where('company_id', $link->company_id)
                        ->exists();

                    if ($canonicalAlreadyLinked) {
                        DB::table('driver_company')->where('id', $link->id)->delete();
                    } else {
                        DB::table('driver_company')->where('id', $link->id)->update(['driver_id' => $canonicalId]);
                    }
                }

                DB::table('deliveries')->where('driver_id', $duplicateId)->update(['driver_id' => $canonicalId]);
                DB::table('drivers')->where('id', $duplicateId)->delete();
            }
        }

        Schema::table('drivers', function (Blueprint $table) {
            // No MySQL a FK depende do índice composto pra existir, então a
            // constraint precisa ser removida antes do índice (ordem inversa
            // não dá erro no SQLite, mas quebra no MySQL).
            $table->dropForeign(['company_id']);
            $table->dropIndex('idx_drivers_company_active');
            $table->dropColumn(['company_id', 'delivery_fee']);
            $table->string('password')->nullable()->after('phone');
            $table->rememberToken()->after('password');
        });

        // Motoristas já cadastrados não têm senha própria ainda (foram criados
        // pelo admin da loja). Preenche com uma senha aleatória inacessível —
        // ainda não existe fluxo de "esqueci minha senha" para motoristas, então
        // essas contas antigas precisam de intervenção manual (suporte) até essa
        // funcionalidade existir.
        DB::table('drivers')->whereNull('password')->update([
            'password' => Hash::make(Str::random(32)),
        ]);

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['password', 'remember_token']);
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->index(['company_id', 'is_active'], 'idx_drivers_company_active');
        });
    }
};
