import {useEffect,type Dispatch,type SetStateAction} from 'react';
import {useLocation} from 'react-router-dom';

/** Alinear anclas despues de cargar el tema evita desplazamientos con geometria provisional. */
export function usePublicNavigation(setOpen:Dispatch<SetStateAction<boolean>>) {
  const location=useLocation();
  useEffect(()=>{
    setOpen(false);document.title='Zentra';
    if(!location.hash){window.scrollTo(0,0);return;}
    let canceled=false,frame=0;
    const cleanups:(()=>void)[]=[];
    const styles=Array.from(document.querySelectorAll<HTMLLinkElement>('link[rel="stylesheet"][href^="/public-assets/"]'));
    const ready=styles.map(link=>link.sheet?Promise.resolve():new Promise<void>(resolve=>{
      const loaded=()=>{link.removeEventListener('load',loaded);link.removeEventListener('error',loaded);resolve();};
      link.addEventListener('load',loaded,{once:true});link.addEventListener('error',loaded,{once:true});
      cleanups.push(loaded);
    }));
    Promise.all(ready).then(()=>document.fonts.ready).then(()=>{
      let previous=-1,stable=0,attempts=0;
      const align=()=>{
        if(canceled)return;
        const target=document.getElementById(location.hash.slice(1));if(!target)return;
        const top=target.getBoundingClientRect().top+window.scrollY;
        stable=Math.abs(top-previous)<0.5?stable+1:0;previous=top;
        if(stable>=3||++attempts>=90){target.scrollIntoView({block:'start'});return;}
        frame=requestAnimationFrame(align);
      };
      if(!canceled)frame=requestAnimationFrame(align);
    });
    return()=>{canceled=true;cancelAnimationFrame(frame);cleanups.forEach(cleanup=>cleanup());};
  },[location.pathname,location.search,location.hash,location.key,setOpen]);
}
