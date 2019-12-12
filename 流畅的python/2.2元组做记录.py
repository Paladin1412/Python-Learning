lax=(33.94,-119.40)
city,year,pop=('Tokyo',"2003","32450")
traveler_id = [('USA','31195855'),('BRA','CE342567')]
for passport in sorted(traveler_id):
    print('%s/%s'%passport)
for country,_ in traveler_id:
     print(country)

"""元组赋值"""
latitude,longitude = lax
"""交换变量值"""
a,b=(2,3)
b,a=a,b
"""元组拆包 *tuple """
c = (2,4)
print(divmod(*c))

e,f,*rest = range(5)
print(rest)