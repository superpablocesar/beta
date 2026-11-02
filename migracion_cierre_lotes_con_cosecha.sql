-- Reconcilia lotes históricos que ya tienen una cosecha pero siguen abiertos.
START TRANSACTION;

INSERT INTO historial_lotes (lote_id, usuario_id, estado_anterior, estado_nuevo, cantidad_afectada, motivo, created_at)
SELECT lc.id, 1, lc.estado_actual, 'Cosechado', 0,
       CONCAT('Conciliación automática por cosecha ya registrada: ', MAX(c.codigo_cosecha)), NOW()
FROM lotes_cultivo lc
INNER JOIN cosechas c ON c.lote_id = lc.id
WHERE lc.estado_actual NOT IN ('Cosechado', 'Descartado')
  AND NOT EXISTS (
      SELECT 1 FROM historial_lotes h
      WHERE h.lote_id = lc.id
        AND h.estado_nuevo = 'Cosechado'
        AND h.motivo LIKE 'Conciliación automática por cosecha ya registrada:%'
  )
GROUP BY lc.id, lc.estado_actual;

UPDATE lotes_cultivo lc
SET lc.estado_actual = 'Cosechado', lc.updated_at = NOW()
WHERE lc.estado_actual NOT IN ('Cosechado', 'Descartado')
  AND EXISTS (SELECT 1 FROM cosechas c WHERE c.lote_id = lc.id);

COMMIT;
