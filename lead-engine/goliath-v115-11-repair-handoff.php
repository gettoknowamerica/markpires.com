<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/config.php';
require_once __DIR__.'/goliath-db.php';

function r11511_key():string{
    if(defined('AFTER_HOURS_CRON_KEY'))return (string)AFTER_HOURS_CRON_KEY;
    if(defined('RETELL_WEBHOOK_KEY'))return (string)RETELL_WEBHOOK_KEY;
    return 'timetomakethedonuts';
}

$key=(string)($_GET['key']??$_POST['key']??'');
if(!hash_equals(r11511_key(),$key)){
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'bad_key']);
    exit;
}

try{
    $archived=0;
    $restored=0;
    $normalized=0;

    // Archive duplicate/orphan sequential tasks. A valid stage task must be the exact task linked by one stage.
    $stmt=gdb()->prepare(
        "UPDATE local_ai_tasks t
         LEFT JOIN goliath_v112_stages s ON s.local_task_id=t.id
         SET t.status='archived',
             t.workflow_state=CASE
                WHEN t.workflow_state IS NOT NULL THEN 'archived'
                ELSE t.workflow_state
             END,
             t.updated_at=NOW()
         WHERE t.task_type='goliath_v112_stage'
         AND t.status IN ('queued','working','claimed','dispatched')
         AND s.id IS NULL"
    );
    try{
        $stmt->execute();
        $archived=$stmt->rowCount();
    }catch(Throwable $ignored){
        // Some installations do not permit "archived" in workflow_state.
        $stmt=gdb()->prepare(
            "UPDATE local_ai_tasks t
             LEFT JOIN goliath_v112_stages s ON s.local_task_id=t.id
             SET t.status='archived',t.updated_at=NOW()
             WHERE t.task_type='goliath_v112_stage'
             AND t.status IN ('queued','working','claimed','dispatched')
             AND s.id IS NULL"
        );
        $stmt->execute();
        $archived=$stmt->rowCount();
    }

    $missions=gdb_all(
        "SELECT id,current_stage_no,status
         FROM goliath_v112_missions
         WHERE status IN ('queued','working')
         ORDER BY priority DESC,id ASC"
    )?:[];

    foreach($missions as $mission){
        $mid=(int)$mission['id'];
        $current=(int)$mission['current_stage_no'];

        // Earlier stages must stay complete; later untouched stages must wait.
        $normalized+=(int)gdb()->exec(
            "UPDATE goliath_v112_stages
             SET status='waiting',local_task_id=NULL,updated_at=NOW()
             WHERE mission_id=".$mid."
             AND stage_no>".$current."
             AND status NOT IN ('complete')"
        );

        $stage=gdb_one(
            "SELECT s.*,t.id task_exists,t.status task_status,t.workflow_state task_workflow
             FROM goliath_v112_stages s
             LEFT JOIN local_ai_tasks t ON t.id=s.local_task_id
             WHERE s.mission_id=? AND s.stage_no=?
             LIMIT 1",
            [$mid,$current]
        );
        if(!$stage)continue;

        if((string)$stage['status']==='complete'){
            $next=gdb_one(
                "SELECT * FROM goliath_v112_stages
                 WHERE mission_id=? AND stage_no>?
                 ORDER BY stage_no ASC LIMIT 1",
                [$mid,$current]
            );
            if($next){
                gdb_update('goliath_v112_stages',[
                    'status'=>'ready',
                    'input_artifact_id'=>$stage['output_artifact_id']??null,
                    'updated_at'=>gdb_now()
                ],'id=:id',['id'=>(int)$next['id']]);
                gdb_update('goliath_v112_missions',[
                    'current_stage_no'=>(int)$next['stage_no'],
                    'status'=>'working',
                    'updated_at'=>gdb_now()
                ],'id=:id',['id'=>$mid]);
                $normalized++;
            }
            continue;
        }

        $taskStatus=strtolower((string)($stage['task_status']??''));
        $taskWorkflow=strtolower((string)($stage['task_workflow']??''));

        if(empty($stage['task_exists'])||in_array($taskStatus,['archived','failed','error'],true)){
            gdb_update('goliath_v112_stages',[
                'status'=>'ready',
                'local_task_id'=>null,
                'last_error'=>'V115.11 restored current stage after missing/orphaned/failed task.',
                'updated_at'=>gdb_now()
            ],'id=:id',['id'=>(int)$stage['id']]);
            $restored++;
        }elseif(
            in_array($taskStatus,['complete','completed','done','success'],true)||
            in_array($taskWorkflow,['complete','completed','done','success'],true)
        ){
            gdb_update('goliath_v112_stages',[
                'status'=>'queued_local',
                'updated_at'=>gdb_now()
            ],'id=:id',['id'=>(int)$stage['id']]);
            $normalized++;
        }elseif(in_array($taskStatus,['queued','working','claimed','dispatched'],true)){
            gdb_update('goliath_v112_stages',[
                'status'=>in_array($taskStatus,['working','claimed'],true)?'working':'queued_local',
                'updated_at'=>gdb_now()
            ],'id=:id',['id'=>(int)$stage['id']]);
            $normalized++;
        }
    }

    $active=gdb_all(
        "SELECT m.id mission_id,m.title,m.originator_key,m.current_stage_no,
                s.id stage_id,s.executive_key,s.stage_key,s.status,s.local_task_id,
                t.status task_status,t.workflow_state task_workflow
         FROM goliath_v112_missions m
         JOIN goliath_v112_stages s
           ON s.mission_id=m.id AND s.stage_no=m.current_stage_no
         LEFT JOIN local_ai_tasks t ON t.id=s.local_task_id
         WHERE m.status IN ('queued','working')
         ORDER BY m.priority DESC,m.id ASC"
    )?:[];

    echo json_encode([
        'ok'=>true,
        'version'=>'V115.11 Handoff Repair',
        'orphan_tasks_archived'=>$archived,
        'stages_restored'=>$restored,
        'rows_normalized'=>$normalized,
        'active_current_stages'=>$active,
        'next'=>'Run goliath-v115-1-sequential-engine.php once, then restart the V115.11 runtime.',
        'time'=>date('c')
    ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

}catch(Throwable $e){
    http_response_code(500);
    echo json_encode([
        'ok'=>false,
        'version'=>'V115.11 Handoff Repair',
        'error'=>'caught_exception',
        'details'=>[
            'message'=>$e->getMessage(),
            'file'=>$e->getFile(),
            'line'=>$e->getLine()
        ]
    ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>