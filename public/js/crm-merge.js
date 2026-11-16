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
  area.innerHTML=candidates.length?'<div class="alert alert-warning"><strong>Contacto existente: no se creará otro registro.</strong><div class="mt-2">'+candidates.map(x=>`<div class="mb-2">#${Number(x.id)} ${safe(x.nombre)} ${safe(x.apellido)} · ${safe(x.email)} · ${safe(x.telefonos)} · ${x.interesado_id?'Ya es prospecto':'Solo contacto'} ${x.interesado_id?'<span class="text-muted">(si hay dos fichas CRM, revisar combinación)</span>':`<button type="button" class="btn btn-sm btn-primary" data-link-id="${Number(x.id)}">Vincular al CRM</button>`}</div>`).join('')+'</div></div>':'';
 }catch(e){area.textContent='No se pudo verificar duplicados: '+e.message;}
}
['prospectCorreoPersonal','prospectCelular','prospectIdentificacion'].forEach(id=>document.getElementById(id)?.addEventListener('change',scan));
area.addEventListener('click',async e=>{
 const btn=e.target.closest('[data-link-id]'); if(!btn)return;
 const id=Number(btn.dataset.linkId);
 const name=btn.closest('.mb-2')?.textContent?.trim() || ('Contacto #'+id);
 if(!confirm('Se vinculará al CRM el contacto existente: '+name+'\nSe conservarán sus datos, correos, teléfonos, tickets e historial.\n¿Confirmar?'))return;
 btn.disabled=true;
 try {
  const body=new URLSearchParams(new FormData(form)); body.set('contacto_id',String(id));
  const response=await fetch(base+'crm/prospectos/vincular-contacto',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body});
  const result=await response.json(); if(!response.ok||!result.success)throw Error(result.error||'No se pudo vincular');
  alert(result.message); location.href=base+'crm/interesados?tab=prospects&open_contact_id='+id;
 } catch(error) {alert('No se pudo vincular: '+error.message);btn.disabled=false;}
});
})();

(() => {
'use strict';
const root=document.getElementById('crmMergeConfig'); if(!root)return;
const base=String(root.dataset.baseUrl||'/').replace(/\/?$/,'/');
const $=id=>document.getElementById(id);
const escape=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const status=(message,error=false)=>{$('crmMergeStatus').textContent=message;$('crmMergeStatus').className=error?'mt-2 text-danger':'mt-2 text-muted';};
let candidates=[],selected=[],comparison=null;
$('crmMergeSearch').addEventListener('click',async()=>{
 const query=new URLSearchParams({correo:$('crmMergeEmail').value.trim(),celular:$('crmMergePhone').value.trim(),identificacion:$('crmMergeIdentity').value.trim()});
 if(![...query.values()].some(Boolean)){status('Ingresa correo, celular o identificación.',true);return;}
 selected=[];comparison=null;$('crmMergeComparison').replaceChildren();status('Buscando…');
 try{
  const response=await fetch(base+'crm/prospectos/duplicados?'+query,{cache:'no-store',headers:{Accept:'application/json'}});
  const data=await response.json();if(!response.ok||!data.success)throw Error(data.error||'No se pudo consultar');
  candidates=data.items||[];
  $('crmMergeMatches').innerHTML=candidates.length?'<p>Selecciona dos prospectos para compararlos:</p>'+candidates.map(c=>`<label class="d-block border rounded p-2 mb-2"><input class="form-check-input me-2" type="checkbox" data-merge-contact="${Number(c.id)}" ${c.interesado_id?'':'disabled'}> <strong>#${Number(c.id)} ${escape(c.nombre)} ${escape(c.apellido)}</strong> · ${escape(c.email)} · ${escape(c.telefonos)} <span class="badge bg-secondary">${c.interesado_id?'Prospecto':'Solo contacto'}</span></label>`).join('')+'<button type="button" class="btn btn-primary" id="crmMergeCompareBtn">Comparar seleccionados</button>':'No se encontraron coincidencias.';
  status(candidates.length+' contacto(s) encontrado(s).');
 }catch(e){status(e.message,true);}
});
$('crmMergeMatches').addEventListener('click',async event=>{
 if(!event.target.closest('#crmMergeCompareBtn'))return;
 const ids=[...$('crmMergeMatches').querySelectorAll('[data-merge-contact]:checked')].map(e=>Number(e.dataset.mergeContact));
 if(ids.length!==2){status('Selecciona exactamente dos prospectos.',true);return;}
 selected=ids;
 try{
  const response=await fetch(base+'crm/prospectos/comparar?'+new URLSearchParams({principal:ids[0],secundario:ids[1]}),{headers:{Accept:'application/json'}});
  const data=await response.json();if(!response.ok||!data.success)throw Error(data.error||'Error');comparison=data.data;
  const p=comparison.principal,s=comparison.secundario;
  if(p.tiene_expediente||s.tiene_expediente)throw Error('No se permite combinar expedientes estudiantiles.');
  const fields=['nombre','apellido','cedula','email'];
  $('crmMergeComparison').innerHTML='<h6>Comparación: principal #'+ids[0]+' / secundario #'+ids[1]+'</h6><p class="text-muted">La primera ficha se conserva; la segunda quedará archivada. Selecciona los valores:</p><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Campo</th><th>Principal</th><th>Secundario</th><th>Conservar</th></tr></thead><tbody>'+fields.map(f=>`<tr><th>${f}</th><td>${escape(p[f])}</td><td>${escape(s[f])}</td><td><select class="form-select form-select-sm" data-merge-field="${f}"><option value="principal">Principal</option><option value="secundario">Secundario</option></select></td></tr>`).join('')+'</tbody></table></div><p>Teléfonos principal: '+escape((p.telefonos||[]).join(', '))+' | secundario: '+escape((s.telefonos||[]).join(', '))+'</p><button id="crmMergeConfirm" class="btn btn-danger" type="button">Confirmar combinación</button>';
  status('Compara los datos antes de confirmar.');
 }catch(e){comparison=null;status(e.message,true);}
});
$('crmMergeComparison').addEventListener('click',async event=>{
 if(!event.target.closest('#crmMergeConfirm')||!comparison)return;
 const choices={};$('crmMergeComparison').querySelectorAll('[data-merge-field]').forEach(el=>choices[el.dataset.mergeField]=el.value);
 if(!confirm('¿Combinar #'+selected[1]+' dentro de #'+selected[0]+'? El secundario se archivará y no existe deshacer automático. Solo confirma si son la misma persona.'))return;
 const btn=$('crmMergeConfirm');btn.disabled=true;
 try{
  const body=new URLSearchParams({_token:$('crmMergeCsrf').value,principal:selected[0],secundario:selected[1],choices:JSON.stringify(choices)});
  const response=await fetch(base+'crm/prospectos/combinar',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded',Accept:'application/json'},body});
  const data=await response.json();if(!response.ok||!data.success)throw Error(data.error||'No se pudo combinar');
  status('Combinación registrada. Actualiza el CRM para ver el contacto principal.');$('crmMergeComparison').replaceChildren();
 }catch(e){status(e.message,true);btn.disabled=false;}
});
})();
