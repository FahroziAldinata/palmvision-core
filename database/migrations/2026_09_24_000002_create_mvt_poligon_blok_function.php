<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION public.mvt_poligon_blok(
                z integer,
                x integer,
                y integer,
                kebun_id text DEFAULT NULL,
                afdeling_id text DEFAULT NULL
            )
            RETURNS bytea AS \$\$
            DECLARE
                bounds geometry;
                mvt bytea;
            BEGIN
                bounds := ST_TileEnvelope(z, x, y);
                SELECT ST_AsMVT(mvtgeom, 'mvt_poligon_blok', 4096, 'geom')
                INTO mvt
                FROM (
                    SELECT
                        b.id AS blok_id,
                        b.kode_blok,
                        b.luas_ha::float AS luas_ha,
                        b.jumlah_pokok,
                        b.kategori_tanah,
                        to_char(b.tanggal_tanam, 'YYYY-MM-DD') AS tanggal_tanam,
                        a.id AS afdeling_id,
                        a.nama AS afdeling_nama,
                        k.id AS kebun_id,
                        k.nama AS kebun_nama,
                        p.versi,
                        ST_AsMVTGeom(
                            ST_Transform(p.poligon, 3857),
                            bounds,
                            4096,
                            64,
                            true
                        ) AS geom
                    FROM blok b
                    JOIN afdeling a ON b.afdeling_id = a.id
                    JOIN kebun k ON a.kebun_id = k.id
                    JOIN (
                        SELECT DISTINCT ON (blok_id) id, blok_id, poligon, versi
                        FROM poligon_blok
                        ORDER BY blok_id, versi DESC
                    ) p ON b.id = p.blok_id
                    WHERE b.deleted_at IS NULL
                      AND ST_Intersects(ST_Transform(p.poligon, 3857), bounds)
                      AND (mvt_poligon_blok.kebun_id IS NULL OR k.id::text = mvt_poligon_blok.kebun_id)
                      AND (mvt_poligon_blok.afdeling_id IS NULL OR a.id::text = mvt_poligon_blok.afdeling_id)
                ) mvtgeom;
                RETURN coalesce(mvt, ''::bytea);
            END;
            \$\$ LANGUAGE plpgsql STABLE PARALLEL SAFE;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS public.mvt_poligon_blok(integer, integer, integer, text, text);');
    }
};
