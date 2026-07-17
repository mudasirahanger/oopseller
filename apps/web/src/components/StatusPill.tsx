export function StatusPill({value}:{value:string}){return <span className={`pill ${value.replaceAll("_","-")}`}>{value.replaceAll("_"," ")}</span>}
