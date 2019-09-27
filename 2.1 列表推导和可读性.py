import array

symbols= '%^&$*#' #返回UNICODE码位
create_tuple = tuple(ord(symbol) for symbol in symbols)
create_array = array.array("I",(ord(symbol) for symbol in symbols))
print(create_tuple,create_array)

##笛卡儿积
a=[1,2,3]
b=[4,5,6,7]
c=[]
# for i in a:
#     for j in b:
#         c.append((i,j))
"""rewrite"""
[c.append((i,j)) for i in a for j in b]

t = [(12,42),(21,5),(2,4,5,9)]
[ print(i,*j) for i,*j in t]