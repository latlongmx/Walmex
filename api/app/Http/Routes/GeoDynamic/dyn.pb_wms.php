<?php


Route::get('/pb_wms', //['middleware' => 'oauth', function() {
function(){
  $date = date('m/d/Y h:i:s a', time());
  Log::info("Map start:".$date);
  //$userId = Authorizer::getResourceOwnerId();
  $MAP = getMapObjConfig();

  $req = new \Owsrequestobj();
  $req->loadparams();

  $COL = $req->getValueByName("col");
  $BOX = $req->getValueByName("bbox"); //-99.1461181640625,19.45105402980001,-99.140625,19.456233596018

  $VALUES = array();
  $MAXVALS = array();
  $GROUPS = 10;
  if($BOX!= ""){

/*
select p.entidad, max(pea)
from inegi.pobviv2010 P,
 inegi.mgn_estados E
where
  ST_Intersects(E.geom,ST_MakeEnvelope(-99.1461181640625,19.45105402980001,-99.140625,19.456233596018, 4326))
  and E.cve_ent = P.entidad
GROUP BY p.entidad, pea;

select p.entidad ent, max(pea)
from inegi.censo_resageburb_2010 P,
 inegi.mgn_estados E
where
  ST_Intersects(E.geom,ST_MakeEnvelope(-99.1461181640625,19.45105402980001,-99.140625,19.456233596018, 4326))
  and E.cve_ent = P.entidad
  and pea not in('N/D','*') and pea is not null
group by p.entidad;
*/
      //$q = "SELECT $COL FROM inegi.pobviv2010 where ST_Intersects(geom,ST_MakeEnvelope('$WKT', 4326))";
      $date = date('m/d/Y h:i:s a', time());
      Log::info("Start Max:".$date);
      //MAXIMOS
      $q = "select p.entidad ent, max($COL) maximo
      from inegi.censo_resageburb_2010 P,
       inegi.mgn_estados E
      where
        ST_Intersects(E.geom,ST_MakeEnvelope($BOX, 4326))
        and E.cve_ent = P.entidad
        and $COL not in('N/D','*') and $COL is not null
      group by p.entidad;";
      $rs = DB::select($q,[]);
      foreach($rs as $r){
        $MAXVALS[] = array("ent" => $r->ent, "max" =>$r->maximo);
      }

      $date = date('m/d/Y h:i:s a', time());
      Log::info("Finish Max:".$date);


      /*$q = "select E.cvegeo cvegeo, $COL variab
      from inegi.censo_resageburb_2010 P,
       inegi.inter15_manzanas E
      where
        ST_Intersects(E.geom,ST_MakeEnvelope($BOX, 4326))
        and E.cvegeo = p.entidad || p.mun || p.loc || p.ageb || p.mza;";
      $rs = DB::select($q,[]);
      foreach($rs as $r){
        $VALUES[] = array("cvegeo" => $r->cvegeo, "variable" => $r->variab);
      }*/
  }
  $date = date('m/d/Y h:i:s a', time());
  Log::info("ConfigLayer:".$date);
  $LAY = getLayerObjConfig($MAP, 'Manzanas', $COL);
  #$LAY->set('data', "geom from (select gid, cvegeo, geom from inegi.inter15_manzanas where ST_Intersects(geom,!BOX!)) as T using unique gid using srid=4326");
  $qry_data = "geom from (
      select E.gid gid, E.cve_ent cve_ent, E.cvegeo cvegeo,
          CASE WHEN P.$COL~E'^\\d+$' THEN P.$COL::numeric ELSE 0 END AS pbvar,
          geom
      from inegi.censo_resageburb_2010 P
      left join inegi.inter15_manzanas E
      on E.cvegeo = P.entidad || P.mun || P.loc || P.ageb || P.mza
      where  ST_Intersects(E.geom, !BOX!) and E.gid is not null
    ) as T using unique gid using srid=4326";
  $LAY->set('data', $qry_data);
  //$LAY->set("classitem", "pbvar");
  $LAY->set('type', MS_LAYER_POLYGON);

  /*foreach ($VALUES as $obj){
    $class = new \ClassObj( $LAY );
    $class->setExpression("(\"[cvegeo]\" = \"".$obj["cvegeo"]."\")");
    $style = new \StyleObj( $class );
    if(is_numeric($obj["variable"]) && (int)$obj["variable"] > 0){
      $mo = array_filter($MAXVALS,function($o) use ($obj){
        return ($o["ent"] == substr($obj["cvegeo"],0,2));
      });
      $MAXVAL = (int)$mo[0]["max"];
      $v = (((int)$obj["variable"])*100)/$MAXVAL;
      $v = $v/100;
      $col = getColorFromColToCol('ffff99', 'ff0000', $v );
      $style->color->setHex( '#'.$col );
    }else{
      $style->color->setHex('#ffff99');
    }
    $style->set('opacity',100);
  }*/
  $date = date('m/d/Y h:i:s a', time());
  Log::info("Config Styles:".$date);
  /*foreach ($MAXVALS as $mx){
    $MAXVALUE = (int)$mx["max"];
    $ENT = $mx["ent"];
    $GG = round($MAXVALUE/$GROUPS);

    $r=0;
    $r2=1;
    $i=1;
    while($r<$MAXVALUE){
      $r2 = $GG*$i;
      $class = new \ClassObj( $LAY );
      $class->setExpression("((\"[cve_ent]\" == \"".$ENT."\") AND ([pbvar] >= ".$r.") AND ([pbvar] < ".$r2."))");
      $style = new \StyleObj( $class );
      $ncol = ((($i*100)/$GROUPS)*0.01);
      $col = getColorFromColToCol('ffff99', 'ff0000', $ncol );
      $style->color->setHex( '#'.$col );
      $style->set('opacity',100);

      $r = $GG*$i;
      $i++;
    }
  }*/
  $date = date('m/d/Y h:i:s a', time());
  Log::info("End Styles:".$date);

  $class = new \ClassObj( $LAY );
  $style = new \StyleObj( $class );
  $style->color->setHex('#ffff99');
  $style->set('opacity',100);

  $date = date('m/d/Y h:i:s a', time());
  Log::info("Buffer:".$date);

  ms_ioinstallstdouttobuffer();
  #$map_file = storage_path("logs/ms_file.map");
  #$MAP->save( $map_file );
  Log::info("Dispatch:".$date);
  $MAP->owsdispatch($req);
    Log::info("Dispatch2:".$date);

  $contenttype = ms_iostripstdoutbuffercontenttype();
  if (!empty($contenttype)){
    error_log($contenttype);
    if ($req->getValueByName('REQUEST') === 'GetCapabilities') {
      $buffer = ms_iogetstdoutbufferstring();
      header('Content-type: application/xml');
      echo $buffer;
    }else{
      $date = date('m/d/Y h:i:s a', time());
      Log::info("Resolv:".$date);
      header('Content-type: $contenttype');
      ms_iogetStdoutBufferBytes();
    }
  }
  else
      echo "Fail to render!";
  ms_ioresethandlers();

});
