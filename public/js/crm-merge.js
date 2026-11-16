(() => {
'use strict';
const form=document.querySelector('#createProspectModal form');
const area=document.getElementById('prospectMergeCreateMatches');
if (!form || !area) return;
const base=String(area.dataset.baseUrl || '/').replace(/\/?$/, '/');
const safe=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const val=id=>document.getElementById(id)?.value?.trim() || '';
const url=(path,params)=>base+path+'?'+new URLSearchParams(params);
let candidates=[];
async function scan(){
 const params={correo:val('prospectCorreoPersonal'),celular:val('prospectCelular'),identificacion:val('prospectIdentificacion')};
 if(!Object.values(params).some(Boolean)){area.innerHTML='';return;}
 try{
  const response=await fetch(url('crm/prospectos/duplicados',params),{cache:'no-store'});
  const data=await response.json();if(!response.ok||!data.success)throw Error(data.error||'Error al buscar');
  candidates=data.items||[];
  area.innerHTML=candidates.length?'<div class="alert alert-warning"><strong>Posibles duplicados: no se creará un nuevo registro hasta revisarlos.</strong><div class="mt-2">'+candidates.map(x=>`<div class="mb-2">#${Number(x.id)} ${safe(x.nombre)} ${safe(x.apellido)} · ${safe(x.email)} · ${safe(x.telefonos)} <button type="button" class="btn btn-sm btn-outline-primary" data-merge-id="${Number(x.id)}">Comparar</button></div>`).join('')+'</div></div>':'';
 }catch(e){area.textContent='No se pudo verificar duplicados: '+e.message;}
}
['prospectCorreoPersonal','prospectCelular','prospectIdentificacion'].forEach(id=>document.getElementById(id)?.addEventListener('change',scan));
area.addEventListener('click',async e=>{
 const btn=e.target.closest('[data-merge-id]');if(!btn)return;
 const other=Number(prompt('ID del segundo prospecto que deseas combinar con el contacto #'+btn.dataset.mergeId+' (no se combinará sin confirmación):',''));
 if(!Number.isSafeInteger(other)||other<=0)return;
 const principal=Number(btn.dataset.mergeId);
 try{
  const r=await fetch(url('crm/prospectos/comparar',{principal,secundario:other}));const d=await r.json();if(!r.ok||!d.success)throw Error(d.error);
  const p=d.data.principal,s=d.data.secundario;
  if(p.tiene_expediente||s.tiene_expediente)throw Error('No se pueden fusionar expedientes estudiantiles.');
  const choices={};let summary='Comparar campos (Aceptar conserva PRINCIPAL; Cancelar conserva SECUNDARIO):\n';
  for(const field of ['nombre','apellido','cedula','email']){
   const keep=confirm(`${field}\nPrincipal #${principal}: ${p[field]||'-'}\nSecundario #${other}: ${s[field]||'-'}\n\nAceptar = principal; Cancelar = secundario`);
   choices[field]=keep?'principal':'secundario';summary+=`${field}: ${choices[field]}\n`;
  }
  if(!confirm(`COMBINACIÓN IRREVERSIBLE SIN RESTAURACIÓN AUTOMÁTICA.\nPrincipal #${principal}; secundario #${other}.\n${summary}\nEl secundario quedará archivado. ¿Confirmas?`))return;
  const token=form.querySelector('[name="_token"]')?.value||'';
  const body=new URLSearchParams({_token:token,principal,secundario:other,choices:JSON.stringify(choices)});
  const done=await fetch(base+'crm/prospectos/combinar',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});const result=await done.json();if(!done.ok||!result.success)throw Error(result.error);
  alert('Combinación registrada. Actualiza el CRM para revisar el contacto principal.');location.reload();
 }catch(err){alert('No se pudo combinar: '+err.message);}
});
})();
