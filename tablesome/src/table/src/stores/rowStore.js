import{writable,readable,derived,get}from"svelte/store";import{LexoRank}from"lexorank";import{tablesome_format,tablesome_isValid,tablesome_PHPDate}from"./../../src/wrapper/date-fns";class RowStore{rows;changedRecords;lastStateRecordID;recordsInserted;recordsUpdated;recordsDeleted;display;constructor(e,t){this.rows=writable(e.rows),this.changedRecords=writable([]),this.mainStore=t,this.lastStateRecordID=writable(e.lastStateRecordID||1),this.recordsInserted=writable([]),this.recordsUpdated=writable(e.recordsUpdated||[]),this.recordsDeleted=writable([]),this.display=e.display}set(e){this.changedRecords.set(e)}get(){return get(this.changedRecords)}addRow(e){e=parseInt(e);let t=this._getEmptyRow(e);const r=get(this.rows),s=this.mainStore.columnStore.get();for(let e=0;e<s.length;e++)t.content.push({type:s[e].format,value:"",html:""});r.splice(e+1,0,t),this.rows.set(r),this.recordInsert(e)}updateRankOrder(e){let t=get(this.mainStore.sortOrder);get(this.mainStore.sortField);const r=get(this.rows);let s="";if("asc"==t){s=LexoRank.min().toString()}else{s=LexoRank.max().toString()}for(let e=0;e<r.length;e++){let o="";o="asc"==t?LexoRank.parse(s).genNext().toString():LexoRank.parse(s).genPrev().toString(),r[e].rank_order=o,s=o}this.rows.set(r)}recordDuplicate(e){e=parseInt(e);const t=this._getEmptyRow(e),r=get(this.rows);t.content=JSON.parse(JSON.stringify(r[e].content)),r.splice(e+1,0,t),this.rows.set(r),this.recordInsert(e)}_getEmptyRow(e){this.lastStateRecordID.update((e=>e+1));const t=get(this.rows),r=t.length,s=t[e].rank_order,o=e+1;let n=get(this.mainStore.sortOrder),a="";if("rank_order"!=get(this.mainStore.sortField)){t[t.length-1].rank_order;a=""}else if(r==o)a=LexoRank.parse(s).genPrev().toString(),a="desc"==n?LexoRank.parse(s).genPrev().toString():LexoRank.parse(s).genNext().toString();else{const e=LexoRank.parse(s),r=LexoRank.parse(t[o].rank_order);a=e.between(r).toString()}return{record_id:0,stateRecordID:get(this.lastStateRecordID),content:[],rank_order:a,created_at:tablesome_PHPDate(),updated_at:tablesome_PHPDate()}}handleDuplicateLexoRank(e,t,r){let s="";if(t<=r.length-1)s=LexoRank.parse(r[t].rank_order).genNext();else{let o=r[t+1];lexoRankOfRowAfterNext=LexoRank.parse(o.rank_order),s=e.between(lexoRankOfRowAfterNext)}return r[t].rank_order=s,this.rows.set(r),s}recordDragged(e,t,r){let s,o,n=get(this.rows),a=get(this.mainStore.sortOrder),d=get(this.mainStore.sortField);if(null!=e&&null!=t){let a=LexoRank.parse(n[e].rank_order),d=LexoRank.parse(n[t].rank_order);o=t-1,o=r>o?t:o,a.toString()==d.toString()&&(d=this.handleDuplicateLexoRank(d,t,n)),s=a.between(d).toString()}else if(null==e){let e="";e="desc"==a&&"rank_order"==d?LexoRank.parse(n[t].rank_order).genNext().toString():LexoRank.parse(n[t].rank_order).genPrev().toString(),o=t<=0?t:t-1,s=e}else if(null==t){let t="";t="desc"==a&&"rank_order"==d?LexoRank.parse(n[e].rank_order).genPrev().toString():LexoRank.parse(n[e].rank_order).genNext().toString(),o=e+1,s=t}r!=o&&(n[r].rank_order=s,this._movePosition(n,r,o),this.rows.set(n),this.recordUpdate(r))}_movePosition(e,t,r){const s=e[t];e.splice(t,1),e.splice(r,0,s)}recordInsert(e){const t=get(this.recordsInserted),r=Object.assign({},get(this.rows)[e+1]);r.record_index=e+1,t.push(r),this.recordsInserted.set(t)}recordUpdate(e){const t=get(this.rows),r=get(this.recordsInserted),s=r.findIndex((r=>r.stateRecordID===t[e].stateRecordID)),o=!r[e]&&-1==s,n=get(this.recordsUpdated),a=n.findIndex((r=>r.stateRecordID===t[e].stateRecordID));!n[e]&&-1==a&&o&&(n.push(t[e]),this.recordsUpdated.set(n))}deleteRow(e){this.recordDelete(e);const t=get(this.rows);t.splice(e,1),this.rows.set(t)}recordDelete(e){if("0"==this.tableID)return;const t=get(this.rows)[e].record_id,r=get(this.recordsInserted),s=get(this.recordsDeleted),o=r.findIndex((t=>t.record_index===e));if(0==t&&-1!==o)return r.splice(o,1),void this.recordsInserted.set(r);s.push(t),this.recordsDeleted.set(s)}}export default RowStore;