<?php

declare(strict_types=1);

/** Fusión conservadora: nunca elimina físicamente registros ni estudiantes. */
final class ProspectMergeService
{
    public function __construct(private PDO $db) {}

    public function find(string $email, string $phone, string $identity, int $exclude = 0): array
    {
        $email = strtolower(trim($email));
        $identity = strtoupper(preg_replace('/[^A-Z0-9]/', '', $identity) ?? '');
        $phone = trim($phone);
        if ($email === '' && $phone === '' && $identity === '') return [];
        $sql = 'SELECT DISTINCT c.id, c.nombre, c.apellido, c.cedula, c.email, c.estado,
                       i.id AS interesado_id, i.carrera, i.modalidad, i.asesor, i.origen, i.estado_id,
                       (SELECT GROUP_CONCAT(DISTINCT t.telefono SEPARATOR ", ") FROM telefonos_contacto t WHERE t.contacto_id=c.id) AS telefonos
                FROM contactos c LEFT JOIN interesados i ON i.contacto_id=c.id
                WHERE c.deleted_at IS NULL AND c.id <> :exclude AND (';
        $conditions=[]; $params=['exclude'=>$exclude];
        if ($email !== '') {
            $conditions[]='LOWER(TRIM(COALESCE(c.email,"")))=:email OR EXISTS (SELECT 1 FROM correos_contacto ce WHERE ce.contacto_id=c.id AND LOWER(TRIM(ce.correo))=:email2)';
            $params['email']=$email; $params['email2']=$email;
        }
        if ($phone !== '') {
            $conditions[]='EXISTS (SELECT 1 FROM telefonos_contacto t WHERE t.contacto_id=c.id AND t.telefono=:phone)';
            $params['phone']=$phone;
        }
        if ($identity !== '') {
            $conditions[]='REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(c.cedula,""))),".",""),"-","")," ","")=:identity';
            $params['identity']=$identity;
        }
        $stmt=$this->db->prepare($sql.implode(' OR ',array_map(fn($x)=>'('.$x.')',$conditions)).') ORDER BY c.id LIMIT 30');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function compare(int $primary, int $secondary): array
    {
        if ($primary <= 0 || $secondary <= 0 || $primary === $secondary) throw new RuntimeException('Selecciona dos contactos diferentes.');
        $stmt=$this->db->prepare('SELECT c.*, i.id AS interesado_id, i.estado_id, i.asesor, i.origen, i.carrera, i.modalidad, i.provincia, i.ciudad FROM contactos c LEFT JOIN interesados i ON i.contacto_id=c.id WHERE c.id IN (?,?) AND c.deleted_at IS NULL');
        $stmt->execute([$primary,$secondary]); $rows=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[(int)$row['id']]=$row;
        if (!isset($rows[$primary],$rows[$secondary])) throw new RuntimeException('Los contactos deben estar activos.');
        foreach ([$primary,$secondary] as $id) {
            $rows[$id]['telefonos']=$this->values('telefonos_contacto','telefono',$id);
            $rows[$id]['correos']=$this->values('correos_contacto','correo',$id);
            $stmt=$this->db->prepare('SELECT COUNT(*) FROM estudiantes WHERE contacto_id=?'); $stmt->execute([$id]);
            $rows[$id]['tiene_expediente']=(int)$stmt->fetchColumn()>0;
        }
        return ['principal'=>$rows[$primary],'secundario'=>$rows[$secondary]];
    }

    private function values(string $table, string $column, int $id): array
    {
        $stmt=$this->db->prepare("SELECT $column FROM $table WHERE contacto_id=? ORDER BY id");
        $stmt->execute([$id]); return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function merge(int $primary, int $secondary, array $choices, int $userId): array
    {
        if ($userId <= 0) throw new RuntimeException('Usuario no identificado.');
        $this->db->beginTransaction();
        try {
            $ids=[$primary,$secondary]; sort($ids);
            $lock=$this->db->prepare('SELECT id FROM contactos WHERE id IN (?,?) FOR UPDATE');
            $lock->execute($ids);
            $pair=$this->compare($primary,$secondary);
            $p=$pair['principal']; $s=$pair['secundario'];
            if ($p['tiene_expediente'] || $s['tiene_expediente']) throw new RuntimeException('No se permite combinar contactos con expediente estudiantil.');
            if (empty($p['interesado_id']) || empty($s['interesado_id'])) throw new RuntimeException('Ambos registros deben ser prospectos CRM.');
            // No fusionar personas diferentes por error: exigir coincidencia comprobable.
            $emails=array_filter(array_merge([(string)$p['email']],$p['correos']));
            $otherEmails=array_filter(array_merge([(string)$s['email']],$s['correos']));
            $sameEmail=(bool)array_intersect(array_map('strtolower',$emails),array_map('strtolower',$otherEmails));
            $samePhone=(bool)array_intersect($p['telefonos'],$s['telefonos']);
            $sameIdentity=trim((string)$p['cedula'])!=='' && strtoupper((string)$p['cedula'])===strtoupper((string)$s['cedula']);
            if (!$sameEmail && !$samePhone && !$sameIdentity) throw new RuntimeException('No hay coincidencia verificable entre los contactos.');
            // No alterar historial vinculado por student_id sin una migración explícita.
            // Las tablas CRM pueden no declarar FK y conservar referencias al secundario.
            $referenceTables = ['crm_student_notes','crm_student_pipeline'];
            foreach ($referenceTables as $table) {
                $exists = $this->db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME="student_id"');
                $exists->execute([$table]);
                if ((int)$exists->fetchColumn() > 0) {
                    $check = $this->db->prepare("SELECT COUNT(*) FROM `$table` WHERE student_id=?");
                    $check->execute([$secondary]);
                    if ((int)$check->fetchColumn() > 0) throw new RuntimeException('El secundario tiene historial CRM en '.$table.'; no se combinará sin migración de ese historial.');
                }
            }
            // No reasignar sin comprobar restricciones y referencias no declaradas.
            $fields=['nombre','apellido','cedula','email']; $updates=[]; $params=['id'=>$primary];
            foreach ($fields as $field) {
                $choice=$choices[$field] ?? 'principal';
                if (!in_array($choice,['principal','secundario'],true)) throw new RuntimeException('Selección inválida: '.$field);
                $updates[]="$field=:$field";
                $params[$field]=$choice==='secundario' ? $s[$field] : $p[$field];
            }
            // Email único: liberar el del secundario solo si se seleccionó, sin borrar el dato archivado.
            if ($params['email'] !== null && $params['email'] !== '' && strcasecmp((string)$params['email'],(string)$s['email'])===0) {
                $this->db->prepare('UPDATE contactos SET email=NULL WHERE id=?')->execute([$secondary]);
            }
            $this->db->prepare('UPDATE contactos SET '.implode(',',$updates).', updated_at=NOW() WHERE id=:id')->execute($params);
            // Canales: conservar los coincidentes en el secundario archivado; trasladar los distintos.
            foreach (['correos_contacto'=>['correo'],'telefonos_contacto'=>['telefono'],'crm_person_channels'=>['channel_type','channel_value'],'crm_student_academic_history'=>['source_user_id']] as $table=>$cols) {
                $where=implode(' AND ',array_map(fn($c)=>"d.$c <=> s.$c",$cols));
                $sql="UPDATE $table s SET s.contacto_id=:primary WHERE s.contacto_id=:secondary AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM $table) d WHERE d.contacto_id=:primary2 AND $where)";
                $this->db->prepare($sql)->execute(['primary'=>$primary,'secondary'=>$secondary,'primary2'=>$primary]);
            }
            // Referencias sin restricciones únicas por contacto.
            foreach (['tickets','bot_conversaciones','campana_destinatarios','cci_campana_destinatarios'] as $table) {
                $this->db->prepare("UPDATE $table SET contacto_id=? WHERE contacto_id=?")->execute([$primary,$secondary]);
            }
            // Conserva la ficha y pipeline secundarios como historial; ocúltalos con soft-delete.
            $this->db->prepare('UPDATE interesados SET estado="inactivo", deleted_at=NOW() WHERE contacto_id=?')->execute([$secondary]);
            $this->db->prepare('UPDATE contactos SET estado="inactivo", deleted_at=NOW() WHERE id=?')->execute([$secondary]);
            $audit=$this->db->prepare('INSERT INTO crm_prospect_merges (primary_contact_id, secondary_contact_id, choices_json, merged_by, merged_at) VALUES (?,?,?,?,NOW())');
            $audit->execute([$primary,$secondary,json_encode($choices,JSON_UNESCAPED_UNICODE),$userId]);
            $this->db->commit();
            return ['principal_id'=>$primary,'secundario_id'=>$secondary];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
