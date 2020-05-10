t_stone=522500
t_iron=236500
t_wood=200500

v_stone = 24697
v_iron=31537
v_wood=29920

extra = 3
now_stone=input("stone:")
now_iron = input("iron:")
now_wood=input("wood:")

t_stone-=int(now_stone)
t_iron-=int(now_iron)
t_wood-=int(now_wood)
ret = []

for a,b in zip(range(0,4),range(3,0,-1)):
    if v_stone+a*30000>t_stone and v_iron+b*30000>t_iron and v_wood+(extra-a-b)*30000>t_wood:
        dicts={}
        dicts['g_stone']=a
        dicts['g_iron']=b
        dicts['g_wood']=extra-b-a
        ret.append(dicts)
print(ret)

